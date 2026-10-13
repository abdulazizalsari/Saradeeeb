<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

final class StoryOfNight
{
    public function enabled(): bool
    {
        return \setting('story_of_night_enabled', '1') === '1';
    }

    public function timezone(): DateTimeZone
    {
        $name = trim(\setting('story_of_night_timezone', (string)\config('timezone', 'Europe/Istanbul')));
        try { return new DateTimeZone($name ?: 'Europe/Istanbul'); }
        catch (\Throwable) { return new DateTimeZone('Europe/Istanbul'); }
    }

    public function current(string $locale, ?DateTimeImmutable $now = null): ?array
    {
        if (!$this->enabled()) return null;
        $now = $now ? $now->setTimezone($this->timezone()) : new DateTimeImmutable('now', $this->timezone());
        $date = $now->format('Y-m-d');
        $override = Database::one("SELECT * FROM story_of_night_overrides WHERE active=1 AND starts_at<=? AND ends_at>=? ORDER BY starts_at DESC,id DESC LIMIT 1", [$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
        $manualId = $override ? (int)$override['article_id'] : 0;
        $selectedArticle = $manualId ? ($this->eligibleArticles($locale, $manualId)[0] ?? null) : null;
        $selectedId = $selectedArticle ? $manualId : 0;
        $mode = $selectedId ? 'manual' : 'automatic';

        $existing = Database::one('SELECT article_id,selection_mode,selected_at FROM story_of_night_selections WHERE feature_date=? AND locale=?', [$date, $locale]);
        if (!$selectedId && $existing && $existing['selection_mode'] === 'automatic') {
            $selectedArticle = $this->eligibleArticles($locale, (int)$existing['article_id'])[0] ?? null;
            if ($selectedArticle) $selectedId = (int)$existing['article_id'];
        }
        if (!$selectedId) {
            $candidates = $this->eligibleArticles($locale);
            if (!$candidates) return null;
            $selectedId = $this->choose($candidates, $locale, $date);
            foreach ($candidates as $candidate) if ((int)$candidate['id'] === $selectedId) { $selectedArticle = $candidate; break; }
        }
        if (!$selectedId || !$selectedArticle) return null;

        Database::exec("INSERT INTO story_of_night_selections(feature_date,locale,article_id,selection_mode,selected_by,selected_at)
            VALUES(?,?,?,?,?,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE article_id=VALUES(article_id),selection_mode=VALUES(selection_mode),selected_by=VALUES(selected_by),selected_at=IF(article_id<=>VALUES(article_id) AND selection_mode=VALUES(selection_mode),selected_at,CURRENT_TIMESTAMP)",
            [$date, $locale, $selectedId, $mode, $override['created_by'] ?? null]);

        $stored=Database::one('SELECT selected_at FROM story_of_night_selections WHERE feature_date=? AND locale=?',[$date,$locale]);
        return $selectedArticle + ['feature_date' => $date, 'selection_mode' => $mode, 'selected_at'=>$stored['selected_at']??null];
    }

    public function eligibleArticles(string $locale = 'ar', ?int $articleId = null): array
    {
        $categoryIds = $this->categoryIds();
        $params = [$locale, $locale, $locale];
        $categoryWhere = '';
        if ($categoryIds) {
            $categoryWhere = ' AND a.category_id IN (' . implode(',', array_fill(0, count($categoryIds), '?')) . ')';
            array_push($params, ...$categoryIds);
        }
        $articleWhere='';
        if($articleId!==null){$articleWhere=' AND a.id=?';$params[]=$articleId;}
        $published = \article_translation_publishable_sql('t');
        return Database::all("SELECT a.id,a.category_id,a.featured_image,a.published_at,a.created_at,a.views,
                t.title,t.slug,t.excerpt,t.content,t.image_alt,
                COALESCE(NULLIF(CASE WHEN wt.status='published' THEN wt.display_name END,''),u.name) author_name,u.username,
                COALESCE(NULLIF(ct.name,''),NULLIF(ar.name,'')) category_name
            FROM articles a
            JOIN article_translations t ON t.article_id=a.id AND t.locale=? AND {$published}
            JOIN users u ON u.id=a.author_id
            LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=?
            LEFT JOIN categories c ON c.id=a.category_id
            LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? AND ct.status='published'
            LEFT JOIN category_translations ar ON ar.category_id=c.id AND ar.locale='ar' AND ar.status='published'
            LEFT JOIN story_of_night_exclusions x ON x.article_id=a.id
            WHERE a.status='published' AND (a.published_at IS NULL OR a.published_at<=CURRENT_TIMESTAMP)
              AND (c.id IS NULL OR c.active=1) AND x.article_id IS NULL {$categoryWhere} {$articleWhere}
            ORDER BY COALESCE(a.published_at,a.created_at) DESC,a.id DESC LIMIT ".($articleId!==null?'1':'500'), $params);
    }

    private function choose(array $candidates, string $locale, string $date): int
    {
        $history = Database::all('SELECT article_id,MAX(feature_date) last_date FROM story_of_night_selections WHERE locale=? AND article_id IS NOT NULL GROUP BY article_id', [$locale]);
        $last = [];
        foreach ($history as $row) $last[(int)$row['article_id']] = (string)$row['last_date'];
        $recentRows = Database::all('SELECT article_id FROM story_of_night_selections WHERE locale=? AND feature_date<? AND feature_date>=DATE_SUB(?,INTERVAL 14 DAY) AND article_id IS NOT NULL', [$locale, $date, $date]);
        $recent = array_fill_keys(array_map(static fn(array $row): int => (int)$row['article_id'], $recentRows), true);
        $pool = array_values(array_filter($candidates, static fn(array $row): bool => !isset($recent[(int)$row['id']])));
        if (!$pool) $pool = $candidates;
        usort($pool, static function (array $a, array $b) use ($last, $date): int {
            $score = static function (array $row) use ($last, $date): array {
                $id = (int)$row['id'];
                $never = !isset($last[$id]) ? 1 : 0;
                $age = $never ? 99999 : max(0, (int)((strtotime($date) - strtotime($last[$id])) / 86400));
                $image = trim((string)($row['featured_image'] ?? '')) !== '' ? 1 : 0;
                return [$never, $age, $image, sprintf('%u', crc32($date . '|' . $id))];
            };
            return $score($b) <=> $score($a);
        });
        return (int)($pool[0]['id'] ?? 0);
    }

    public function categoryIds(): array
    {
        $decoded = json_decode(\setting('story_of_night_categories', '[]'), true);
        if (!is_array($decoded)) return [];
        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $id): bool => $id > 0)));
    }

    public function history(int $limit = 40): array
    {
        $limit = max(1, min(200, $limit));
        return Database::all("SELECT s.*,t.title,u.name author_name FROM story_of_night_selections s
            LEFT JOIN article_translations t ON t.article_id=s.article_id AND t.locale=s.locale
            LEFT JOIN articles a ON a.id=s.article_id LEFT JOIN users u ON u.id=a.author_id
            ORDER BY s.feature_date DESC,s.locale LIMIT {$limit}");
    }

    public function exclusions(): array
    {
        return Database::all("SELECT x.*,t.title,u.name author_name FROM story_of_night_exclusions x JOIN articles a ON a.id=x.article_id LEFT JOIN article_translations t ON t.article_id=a.id AND t.locale='ar' JOIN users u ON u.id=a.author_id ORDER BY x.created_at DESC");
    }

    public function activeOverride(?DateTimeImmutable $now = null): ?array
    {
        $now = $now ? $now->setTimezone($this->timezone()) : new DateTimeImmutable('now', $this->timezone());
        return Database::one("SELECT o.*,t.title FROM story_of_night_overrides o LEFT JOIN article_translations t ON t.article_id=o.article_id AND t.locale='ar' WHERE o.active=1 AND o.starts_at<=? AND o.ends_at>=? ORDER BY o.starts_at DESC,o.id DESC LIMIT 1", [$now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
    }
}
