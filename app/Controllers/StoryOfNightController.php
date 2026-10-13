<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\StoryOfNight;
use DateTimeImmutable;
use DateTimeZone;

final class StoryOfNightController
{
    private function admin(): array { Auth::requireRole(['admin']); return Auth::user() ?? []; }

    public function index(): void
    {
        $this->admin();
        $service = new StoryOfNight();
        $current = $service->enabled() ? $service->current('ar') : null;
        $history = $service->history();
        $exclusions = $service->exclusions();
        $override = $service->activeOverride();
        $articles = $service->eligibleArticles('ar');
        $categories = Database::all("SELECT c.id,COALESCE(ar.name,CONCAT('تصنيف ',c.id)) name FROM categories c LEFT JOIN category_translations ar ON ar.category_id=c.id AND ar.locale='ar' WHERE c.active=1 ORDER BY c.sort_order,c.id");
        $selectedCategories = $service->categoryIds();
        View::render('admin/story-of-night', compact('current','history','exclusions','override','articles','categories','selectedCategories') + ['enabled'=>$service->enabled(),'timezone'=>$service->timezone()->getName(),'title'=>'قصة الليلة'], 'dashboard');
    }

    public function saveSettings(): void
    {
        $this->admin(); Csrf::enforce();
        $timezone = trim((string)($_POST['timezone'] ?? 'Europe/Istanbul'));
        try { new DateTimeZone($timezone); } catch (\Throwable) { $timezone = 'Europe/Istanbul'; }
        $categories = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['categories'] ?? [])), static fn(int $id): bool => $id > 0)));
        $this->put('story_of_night_enabled', isset($_POST['enabled']) ? '1' : '0');
        $this->put('story_of_night_timezone', $timezone);
        $this->put('story_of_night_categories', json_encode($categories));
        audit_log('story_of_night_settings','setting',null,json_encode(['enabled'=>isset($_POST['enabled']),'timezone'=>$timezone,'categories'=>$categories]));
        flash('success','تم حفظ إعدادات قصة الليلة.'); redirect('/admin/story-of-night');
    }

    public function setOverride(): void
    {
        $admin=$this->admin(); Csrf::enforce(); $service=new StoryOfNight();
        $articleId=(int)($_POST['article_id']??0); $eligible=array_column($service->eligibleArticles('ar'),'id');
        $tz=$service->timezone();
        try {
            if(!in_array($articleId,array_map('intval',$eligible),true)) throw new \RuntimeException('اختر مقالًا عربيًا منشورًا ومؤهلًا.');
            $startRaw=trim((string)($_POST['starts_at']??'')); $endRaw=trim((string)($_POST['ends_at']??''));
            $now=new DateTimeImmutable('now',$tz);
            $start=$startRaw!==''?new DateTimeImmutable($startRaw,$tz):$now->setTime(0,0);
            $end=$endRaw!==''?new DateTimeImmutable($endRaw,$tz):$now->setTime(23,59,59);
            if($end<=$start) throw new \RuntimeException('يجب أن ينتهي التعيين بعد وقت بدايته.');
            Database::transaction(function()use($articleId,$start,$end,$admin){
                Database::exec('UPDATE story_of_night_overrides SET active=0 WHERE active=1');
                Database::insert('INSERT INTO story_of_night_overrides(article_id,starts_at,ends_at,active,created_by) VALUES(?,?,?,?,?)',[$articleId,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),1,(int)$admin['id']]);
            });
            audit_log('story_of_night_override','article',$articleId,$start->format(DATE_ATOM).' — '.$end->format(DATE_ATOM));
            flash('success','تم تفعيل الاختيار اليدوي خلال المدة المحددة.');
        } catch(\Throwable $e){ flash('error',$e->getMessage()); }
        redirect('/admin/story-of-night');
    }

    public function clearOverride(): void
    {
        $this->admin(); Csrf::enforce(); Database::exec('UPDATE story_of_night_overrides SET active=0 WHERE active=1');
        audit_log('story_of_night_automatic','setting',null,'manual override cleared'); flash('success','عادت قصة الليلة إلى الاختيار التلقائي.'); redirect('/admin/story-of-night');
    }

    public function exclude(): void
    {
        $admin=$this->admin(); Csrf::enforce(); $articleId=(int)($_POST['article_id']??0);
        $exists=Database::one("SELECT id FROM articles WHERE id=? AND status='published'",[$articleId]);
        if(!$exists){flash('error','المقال غير موجود أو غير منشور.');redirect('/admin/story-of-night');}
        Database::exec('INSERT INTO story_of_night_exclusions(article_id,excluded_by,reason) VALUES(?,?,?) ON DUPLICATE KEY UPDATE excluded_by=VALUES(excluded_by),reason=VALUES(reason),created_at=CURRENT_TIMESTAMP',[$articleId,(int)$admin['id'],mb_substr(trim((string)($_POST['reason']??'')),0,255)]);
        audit_log('story_of_night_exclude','article',$articleId,''); flash('success','تم استبعاد المقال من الاختيار التلقائي.'); redirect('/admin/story-of-night');
    }

    public function include(): void
    {
        $this->admin(); Csrf::enforce(); $articleId=(int)($_POST['article_id']??0); Database::exec('DELETE FROM story_of_night_exclusions WHERE article_id=?',[$articleId]);
        audit_log('story_of_night_include','article',$articleId,''); flash('success','أعيد المقال إلى قائمة الاختيار التلقائي.'); redirect('/admin/story-of-night');
    }

    private function put(string $key,string $value): void { Database::exec('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); }
}
