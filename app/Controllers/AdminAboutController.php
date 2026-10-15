<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\AboutPage;
use App\Services\BehindPage;

final class AdminAboutController
{
    private function guard(): array { Auth::requireRole(['admin']); return Auth::user(); }

    private function locale(): string
    {
        $locale=(string)($_REQUEST['locale']??'ar');
        if(!isset(article_locales()[$locale])){ http_response_code(422); exit('اللغة غير مفعّلة في الموقع.'); }
        return $locale;
    }

    public function index(): void
    {
        $this->guard();
        $locale=$this->locale();
        $version=AboutPage::editable($locale);
        $published=AboutPage::version($locale,'published');
        $history=AboutPage::history($locale);
        $translation=Database::one('SELECT title,slug,status FROM page_translations WHERE page_id=? AND locale=?',[BehindPage::id(),$locale]);
        $mediaLibrary=Database::all("SELECT id,path,title,alt_text,original_name FROM media WHERE mime LIKE 'image/%' AND deleted_at IS NULL ORDER BY id DESC LIMIT 160");
        View::render('admin/about-editor',compact('locale','version','published','history','translation','mediaLibrary')+['title'=>'التعريف بمنصة سراديب'],'dashboard');
    }

    public function save(): void
    {
        $user=$this->guard(); Csrf::enforce(); $locale=$this->locale();
        try {
            $id=AboutPage::saveDraft($locale,(array)($_POST['fields']??[]),[
                'seo_title'=>$_POST['seo_title']??'', 'seo_description'=>$_POST['seo_description']??'',
                'image_path'=>$_POST['image_path']??'', 'image_alt'=>$_POST['image_alt']??''
            ],(int)$user['id']);
            audit_log('about.draft_saved','about_page',$id,'locale='.$locale);
            flash('success','حُفظت المسودة. النسخة المنشورة ما زالت كما هي.');
            redirect('/admin/about?locale='.rawurlencode($locale));
        } catch(\InvalidArgumentException $e){ http_response_code(422); exit(e($e->getMessage())); }
    }

    public function publish(): void
    {
        $user=$this->guard(); Csrf::enforce(); $locale=$this->locale();
        try {
            $id=AboutPage::publish($locale,(int)$user['id']);
            audit_log('about.published','about_page',$id,'locale='.$locale);
            flash('success','نُشرت نسخة التعريف بهذه اللغة بنجاح.');
            redirect('/admin/about?locale='.rawurlencode($locale));
        } catch(\RuntimeException $e){ http_response_code(422); exit(e($e->getMessage())); }
    }

    public function restore(): void
    {
        $user=$this->guard(); Csrf::enforce(); $locale=$this->locale();
        try {
            $id=AboutPage::restore((int)($_POST['version_id']??0),$locale,(int)$user['id']);
            audit_log('about.version_restored','about_page',$id,'locale='.$locale);
            flash('success','استُعيدت النسخة السابقة ونُشرت.');
            redirect('/admin/about?locale='.rawurlencode($locale));
        } catch(\RuntimeException $e){ http_response_code(422); exit(e($e->getMessage())); }
    }

    public function preview(): void
    {
        $this->guard(); $locale=$this->locale();
        $version=AboutPage::version($locale,'draft') ?? AboutPage::version($locale,'published');
        if(!$version){ http_response_code(404); exit('لا يوجد محتوى لمعاينته.'); }
        $page=Database::one('SELECT p.*,pt.slug,pt.canonical_url FROM pages p JOIN page_translations pt ON pt.page_id=p.id AND pt.locale=? WHERE p.id=?',[$locale,BehindPage::id()]);
        $about=$version['fields']; $isPreview=true;
        View::render('public/about',compact('locale','about','version','page','isPreview')+[
            'title'=>$version['seo_title']?:$about['platform_name'],
            'description'=>$version['seo_description']?:$about['short_intro'],
            'robots'=>'noindex,nofollow',
            'canonical'=>BehindPage::url($locale)?:url('/'),
            'ogImage'=>$version['image_path']??''
        ],'public');
    }
}
