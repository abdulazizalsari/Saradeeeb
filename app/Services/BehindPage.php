<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

final class BehindPage
{
    public static function id(): int
    {
        $row = Database::one("SELECT value FROM settings WHERE `key`='behind_page_id'");
        return (int)($row['value'] ?? 0);
    }

    /** Adopt existing content once; never recreate a deleted page or overwrite edits. */
    public static function ensure(): void
    {
        if (self::id()) return;
        Database::transaction(static function (): void {
            Database::exec("INSERT IGNORE INTO settings(`key`,value) VALUES('behind_page_id','0')");
            $marker = Database::one("SELECT value FROM settings WHERE `key`='behind_page_id' FOR UPDATE");
            if ((int)$marker['value']) return;
            $existing = Database::one("SELECT p.id FROM pages p JOIN page_translations pt ON pt.page_id=p.id WHERE pt.locale='ar' AND pt.slug IN ('من-خلف-سراديب','behind-saradeeb') ORDER BY p.id LIMIT 1");
            $id = (int)($existing['id'] ?? 0);
            if (!$id) {
                $id = Database::insert("INSERT INTO pages(status,sort_order,template) VALUES('published',0,'standard')");
                foreach (\all_locales() as $locale => $info) {
                    // Reuse the original editorial copy when moving it into the page editor.
                    ob_start();
                    try { require BASE_PATH.'/app/Views/public/behind-saradeeb.php'; }
                    finally { ob_end_clean(); }
                    $content = '<p>'.\e($page['intro']).'</p><h2>'.\e($page['purpose_title']).'</h2><p>'.\e($page['purpose']).'</p><h2>'.\e($page['values_title']).'</h2>';
                    foreach ($page['values'] as [$heading, $text]) $content .= '<h3>'.\e($heading).'</h3><p>'.\e($text).'</p>';
                    $content .= '<h2>'.\e($page['cta_title']).'</h2><p>'.\e($page['cta_text']).'</p><p><a class="btn btn-primary" href="'.\e(\locale_url($locale,'send-story')).'">'.\e($page['story']).'</a> <a class="btn btn-ghost" href="'.\e(\locale_url($locale,'writer-application')).'">'.\e($page['writer']).'</a></p>';
                    Database::insert("INSERT INTO page_translations(page_id,locale,title,slug,content,content_mode,status,review_state,show_title,page_bg_color,page_text_color,content_width) VALUES(?,?,?,?,?,'visual','published','approved',1,'#071019','#c7d7db','standard')", [$id,$locale,$page['hero'],$locale==='ar'?'من-خلف-سراديب':'behind-saradeeb',$content]);
                }
            }
            Database::exec("UPDATE settings SET value=? WHERE `key`='behind_page_id'", [(string)$id]);
        });
    }

    public static function published(string $locale): ?array
    {
        $id = self::id();
        if (!$id) return null;
        $sql = "SELECT pt.locale,pt.slug,pt.title FROM pages p JOIN page_translations pt ON pt.page_id=p.id WHERE p.id=? AND p.status='published' AND p.deleted_at IS NULL AND pt.locale=? AND ".\page_translation_publishable_sql('pt');
        $page = Database::one($sql, [$id,$locale]);
        return $page;
    }

    public static function url(string $locale): string
    {
        $page = self::published($locale);
        return $page ? \locale_url($page['locale'],'page/'.$page['slug']) : '';
    }
}
