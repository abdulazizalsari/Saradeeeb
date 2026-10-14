<?php
namespace App\Services;

use App\Core\Database;

/** Shared, idempotent publication job used by CLI and the protected HTTP cron. */
final class ScheduledPublisher {
    public static function run(): int {
        $published = Database::exec("UPDATE articles a JOIN article_translations ar ON ar.article_id=a.id AND ar.locale='ar' SET a.status='published',a.updated_at=CURRENT_TIMESTAMP WHERE a.status IN ('draft','review') AND a.published_at IS NOT NULL AND a.published_at<=CURRENT_TIMESTAMP AND ar.status='published' AND ar.review_state='approved' AND TRIM(ar.title)<>'' AND TRIM(ar.slug)<>'' AND TRIM(ar.content)<>''");
        try {
            $storyOfNight = new StoryOfNight();
            if ($storyOfNight->enabled()) foreach (array_keys(enabled_locales()) as $locale) $storyOfNight->current((string)$locale);
        } catch (\Throwable $error) {
            // Serverless filesystems are not persistent; Vercel captures error_log.
            error_log('Saradeeeb scheduled publisher: ' . $error->getMessage());
        }
        return $published;
    }
}
