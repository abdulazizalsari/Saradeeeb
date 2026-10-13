<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

final class AboutPage
{
    public const RICH_FIELDS = ['full_intro','vision','mission','goals','specialties','content_types','editorial_philosophy','writer_offers','joining_info','review_info','contact_info'];
    public const PLAIN_FIELDS = ['platform_name','short_intro'];

    public static function pageId(): int { return BehindPage::id(); }

    public static function ensure(): void
    {
        $pageId=self::pageId();
        if(!$pageId) return;
        foreach(Database::all('SELECT * FROM page_translations WHERE page_id=?',[$pageId]) as $translation){
            $exists=Database::one("SELECT id FROM about_page_versions WHERE page_id=? AND locale=? AND status='published' LIMIT 1",[$pageId,$translation['locale']]);
            if($exists) continue;
            $content=\sanitize_page_html((string)($translation['content']??''));
            $plain=trim(preg_replace('/\s+/u',' ',strip_tags($content))??'');
            $data=self::emptyData();
            $data['platform_name']=(string)$translation['title'];
            $data['short_intro']=mb_substr($plain,0,260);
            $data['full_intro']=$content;
            Database::insert("INSERT INTO about_page_versions(page_id,locale,status,content_json,seo_title,seo_description,image_path,image_alt,published_at) VALUES(?,?,'published',?,?,?,?,?,NOW())",[
                $pageId,$translation['locale'],self::encode($data),(string)($translation['seo_title']??''),(string)($translation['seo_description']??''),(string)($translation['featured_image']??''),(string)$translation['title']
            ]);
        }
        // Repair excerpts seeded from legacy WordPress blocks where strip_tags()
        // exposed the contents of an embedded <style> element as visible text.
        foreach(Database::all("SELECT id,content_json FROM about_page_versions WHERE page_id=? AND status='published'",[$pageId]) as $row){
            $data=self::decode((string)$row['content_json']);
            if(!str_contains($data['short_intro'],'{') && !str_contains($data['short_intro'],'--primary-color')) continue;
            $plain=trim(preg_replace('/\s+/u',' ',strip_tags(\sanitize_page_html($data['full_intro'])))??'');
            $data['short_intro']=mb_substr($plain,0,260);
            Database::exec('UPDATE about_page_versions SET content_json=? WHERE id=?',[self::encode($data),$row['id']]);
        }
        // Adopt the existing page under the approved public About naming once.
        // Exact-match guards preserve any title an administrator customized.
        if(\setting('about_page_title_migrated','0')!=='1'){
            $titles=[
                'ar'=>['من خلف سراديب','عن سراديب'],'en'=>['Behind Saradeeeb','About Saradeeeb'],
                'es'=>['Detrás de Saradeeeb','Acerca de Saradeeeb'],'fr'=>['Dans les coulisses de Saradeeeb','À propos de Saradeeeb'],
                'ru'=>['За кулисами Saradeeeb','О Saradeeeb'],
            ];
            foreach($titles as $locale=>[$old,$new]){
                $translation=Database::one('SELECT title FROM page_translations WHERE page_id=? AND locale=?',[$pageId,$locale]);
                if(($translation['title']??'')!==$old) continue;
                Database::exec('UPDATE page_translations SET title=? WHERE page_id=? AND locale=?',[$new,$pageId,$locale]);
                foreach(Database::all('SELECT id,content_json FROM about_page_versions WHERE page_id=? AND locale=?',[$pageId,$locale]) as $version){
                    $data=self::decode((string)$version['content_json']);
                    if($data['platform_name']!==$old) continue;
                    $data['platform_name']=$new;
                    Database::exec('UPDATE about_page_versions SET content_json=? WHERE id=?',[self::encode($data),$version['id']]);
                }
            }
            Database::exec("INSERT INTO settings(`key`,value) VALUES('about_page_title_migrated','1') ON DUPLICATE KEY UPDATE value='1'");
        }
    }

    public static function emptyData(): array
    {
        return array_fill_keys(array_merge(self::PLAIN_FIELDS,self::RICH_FIELDS),'');
    }

    public static function decode(string $json): array
    {
        $value=json_decode($json,true);
        return array_replace(self::emptyData(),is_array($value)?array_intersect_key($value,self::emptyData()):[]);
    }

    private static function encode(array $data): string
    {
        return json_encode(array_replace(self::emptyData(),array_intersect_key($data,self::emptyData())),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public static function version(string $locale,string $status='published'): ?array
    {
        $row=Database::one("SELECT * FROM about_page_versions WHERE page_id=? AND locale=? AND status=? ORDER BY id DESC LIMIT 1",[self::pageId(),$locale,$status]);
        if($row) $row['fields']=self::decode((string)$row['content_json']);
        return $row;
    }

    public static function editable(string $locale): array
    {
        return self::version($locale,'draft') ?? self::version($locale,'published') ?? ['id'=>0,'locale'=>$locale,'status'=>'draft','fields'=>self::emptyData(),'seo_title'=>'','seo_description'=>'','image_path'=>'','image_alt'=>'','updated_at'=>null];
    }

    public static function saveDraft(string $locale,array $fields,array $meta,int $userId): int
    {
        $safe=self::emptyData();
        foreach(self::PLAIN_FIELDS as $key) $safe[$key]=mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)($fields[$key]??'')))??''),0,$key==='short_intro'?700:190);
        foreach(self::RICH_FIELDS as $key) $safe[$key]=\sanitize_page_html((string)($fields[$key]??''));
        if($safe['platform_name']==='') throw new \InvalidArgumentException('اسم المنصة مطلوب.');
        $image=trim((string)($meta['image_path']??''));
        if($image!=='' && !self::approvedImage($image)) throw new \InvalidArgumentException('اختر صورة من مكتبة الوسائط المعتمدة.');
        $seoTitle=mb_substr(trim(strip_tags((string)($meta['seo_title']??''))),0,255);
        $seoDescription=mb_substr(trim(strip_tags((string)($meta['seo_description']??''))),0,320);
        $imageAlt=mb_substr(trim(strip_tags((string)($meta['image_alt']??''))),0,255);
        $draft=self::version($locale,'draft');
        if($draft){
            Database::exec('UPDATE about_page_versions SET content_json=?,seo_title=?,seo_description=?,image_path=?,image_alt=?,created_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[self::encode($safe),$seoTitle,$seoDescription,$image,$imageAlt,$userId,$draft['id']]);
            return (int)$draft['id'];
        }
        return Database::insert("INSERT INTO about_page_versions(page_id,locale,status,content_json,seo_title,seo_description,image_path,image_alt,created_by) VALUES(?,?,'draft',?,?,?,?,?,?)",[self::pageId(),$locale,self::encode($safe),$seoTitle,$seoDescription,$image,$imageAlt,$userId]);
    }

    public static function publish(string $locale,int $userId): int
    {
        $draft=self::version($locale,'draft');
        if(!$draft) throw new \RuntimeException('احفظ مسودة لهذه اللغة قبل نشرها.');
        return Database::transaction(function() use($draft,$locale,$userId): int {
            Database::exec("UPDATE about_page_versions SET status='archived' WHERE page_id=? AND locale=? AND status='published'",[self::pageId(),$locale]);
            Database::exec("UPDATE about_page_versions SET status='published',published_by=?,published_at=NOW(),updated_at=CURRENT_TIMESTAMP WHERE id=?",[$userId,$draft['id']]);
            $fields=self::decode((string)$draft['content_json']);
            Database::exec("UPDATE page_translations SET title=?,seo_title=?,seo_description=?,featured_image=?,status='published',review_state='approved' WHERE page_id=? AND locale=?",[$fields['platform_name'],$draft['seo_title'],$draft['seo_description'],$draft['image_path'],self::pageId(),$locale]);
            Database::exec("UPDATE pages SET status='published',updated_at=CURRENT_TIMESTAMP WHERE id=?",[self::pageId()]);
            return (int)$draft['id'];
        });
    }

    public static function restore(int $versionId,string $locale,int $userId): int
    {
        $source=Database::one("SELECT * FROM about_page_versions WHERE id=? AND page_id=? AND locale=? AND status='archived'",[$versionId,self::pageId(),$locale]);
        if(!$source) throw new \RuntimeException('النسخة السابقة غير موجودة.');
        return Database::transaction(function() use($source,$locale,$userId): int {
            Database::exec("UPDATE about_page_versions SET status='archived' WHERE page_id=? AND locale=? AND status='published'",[self::pageId(),$locale]);
            $id=Database::insert("INSERT INTO about_page_versions(page_id,locale,status,content_json,seo_title,seo_description,image_path,image_alt,created_by,published_by,published_at) VALUES(?,?,'published',?,?,?,?,?,?,?,NOW())",[self::pageId(),$locale,$source['content_json'],$source['seo_title'],$source['seo_description'],$source['image_path'],$source['image_alt'],$userId,$userId]);
            $fields=self::decode((string)$source['content_json']);
            Database::exec("UPDATE page_translations SET title=?,seo_title=?,seo_description=?,featured_image=?,status='published',review_state='approved' WHERE page_id=? AND locale=?",[$fields['platform_name'],$source['seo_title'],$source['seo_description'],$source['image_path'],self::pageId(),$locale]);
            return $id;
        });
    }

    public static function history(string $locale): array
    {
        return Database::all("SELECT v.id,v.status,v.published_at,v.updated_at,u.name publisher_name FROM about_page_versions v LEFT JOIN users u ON u.id=v.published_by WHERE v.page_id=? AND v.locale=? AND v.status IN ('published','archived') ORDER BY COALESCE(v.published_at,v.updated_at) DESC LIMIT 20",[self::pageId(),$locale]);
    }

    private static function approvedImage(string $path): bool
    {
        if($path===(string)\setting('brand_logo','')) return true;
        return Database::one('SELECT id FROM media WHERE path=? AND mime LIKE ? AND deleted_at IS NULL LIMIT 1',[$path,'image/%'])!==null;
    }
}
