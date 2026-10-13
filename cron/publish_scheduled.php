<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/bootstrap.php';
use App\Core\Database;
Database::exec("UPDATE articles a JOIN article_translations ar ON ar.article_id=a.id AND ar.locale='ar' SET a.status='published' WHERE a.status IN ('draft','review') AND a.published_at IS NOT NULL AND a.published_at<=CURRENT_TIMESTAMP AND ar.status='published' AND ar.review_state='approved' AND TRIM(ar.title)<>'' AND TRIM(ar.slug)<>'' AND TRIM(ar.content)<>''");
// Translation publication is explicit; scheduling the mother article must never
// turn a foreign draft into public content.
try {
    $storyOfNight=new \App\Services\StoryOfNight();
    if($storyOfNight->enabled()) foreach(array_keys(enabled_locales()) as $locale) $storyOfNight->current((string)$locale);
} catch (\Throwable $error) {
    @file_put_contents(BASE_PATH.'/storage/story-of-night-cron.log',date('c').' '.$error->getMessage().PHP_EOL,FILE_APPEND);
}
echo "Scheduled publishing checked at ".date('c').PHP_EOL;
