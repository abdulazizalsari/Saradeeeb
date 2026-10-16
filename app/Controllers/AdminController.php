<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Core\Integrations;
use App\Services\DatabaseBackup;

final class AdminController {
    private function contentGuard(): array {
        Auth::requireRole(['admin','editor']);
        return Auth::user();
    }
    private function writerManagementGuard(): array {
        Auth::requireRole(['admin','editor']);
        return Auth::user();
    }
    private function adminGuard(): array {
        Auth::requireRole(['admin']);
        return Auth::user();
    }

    public function dashboard(): void {
        $u = $this->contentGuard();
        $fullAdmin = ($u['role'] ?? '') === 'admin';
        $stats = [
            'articles'=>(int)(Database::one("SELECT COUNT(*) c FROM articles")['c']??0),
            'published'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='published'")['c']??0),
            'review'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='review'")['c']??0),
            'drafts'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='draft'")['c']??0),
            'changes'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='changes'")['c']??0),
            'writers'=>(int)(Database::one("SELECT COUNT(*) c FROM users WHERE role IN ('writer','trusted_writer')")['c']??0),
            'applications'=>(int)(Database::one("SELECT COUNT(*) c FROM writer_applications WHERE status='new'")['c']??0),
            'views'=>(int)(Database::one('SELECT COALESCE(SUM(views),0) c FROM articles')['c']??0),
            'translations'=>(int)(Database::one('SELECT COUNT(*) c FROM article_translations')['c']??0),
            'pages'=>$fullAdmin?(int)(Database::one('SELECT COUNT(*) c FROM pages')['c']??0):0,
            'ads'=>$fullAdmin?(int)(Database::one('SELECT COUNT(*) c FROM ads WHERE active=1')['c']??0):0,
            'messages'=>$fullAdmin?(int)(Database::one("SELECT COUNT(*) c FROM contact_messages WHERE status='new'")['c']??0):0,
            'stories'=>$fullAdmin?(int)(Database::one("SELECT COUNT(*) c FROM story_submissions WHERE status='new'")['c']??0):0,
            'subscribers'=>$fullAdmin?(int)(Database::one("SELECT COUNT(*) c FROM newsletter_subscribers WHERE status='active'")['c']??0):0,
            'community'=>$fullAdmin?(int)(Database::one("SELECT COUNT(*) c FROM community_topics WHERE locale='ar' AND status IN ('open','closed')")['c']??0):0,
        ];
        $default=default_locale(); $recent = Database::all("SELECT a.id,a.status,a.updated_at,u.name author_name,(SELECT title FROM article_translations t WHERE t.article_id=a.id ORDER BY (t.locale=?) DESC,t.id LIMIT 1) title FROM articles a JOIN users u ON u.id=a.author_id ORDER BY a.updated_at DESC LIMIT 10",[$default]);
        View::render('admin/dashboard', compact('u','stats','recent','fullAdmin') + ['title'=>'مركز إدارة سراديب'], 'dashboard');
    }

    public function mobileApp(): void {
        $u=$this->contentGuard();
        $fullAdmin=($u['role']??'')==='admin';
        $stats=[
            'review'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='review'")['c']??0),
            'changes'=>(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='changes'")['c']??0),
            'comments'=>(int)(Database::one("SELECT COUNT(*) c FROM article_comments WHERE status='pending'")['c']??0),
            'applications'=>(int)(Database::one("SELECT COUNT(*) c FROM writer_applications WHERE status='new'")['c']??0),
            'messages'=>$fullAdmin?(int)(Database::one("SELECT COUNT(*) c FROM contact_messages WHERE status='new'")['c']??0):0,
        ];
        try{$stats['editorial']=(int)(Database::one("SELECT COUNT(*) c FROM editorial_messages m JOIN editorial_threads t ON t.id=m.thread_id WHERE m.read_at IS NULL AND m.sender_id<>?",[(int)$u['id']])['c']??0);}catch(\Throwable){$stats['editorial']=0;}
        $default=default_locale();
        $reviews=Database::all("SELECT a.id,a.status,a.updated_at,u.name author_name,(SELECT title FROM article_translations t WHERE t.article_id=a.id ORDER BY (t.locale=?) DESC,t.id LIMIT 1) title FROM articles a JOIN users u ON u.id=a.author_id WHERE a.status IN ('review','changes') ORDER BY a.updated_at DESC LIMIT 8",[$default]);
        $notifications=[]; try{$notifications=Database::all("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 8",[(int)$u['id']]);}catch(\Throwable){}
        View::render('admin/mobile-app',compact('u','stats','reviews','notifications','fullAdmin')+['title'=>'سراديب Admin'],'dashboard');
    }

    public function articles(): void {
        $this->contentGuard();
        $default=default_locale();
        $q=trim((string)($_GET['q']??''));
        $category=(int)($_GET['category']??0); $author=(int)($_GET['author']??0);
        $status=(string)($_GET['status']??''); $sort=(string)($_GET['sort']??'date_desc');
        $where=[]; $params=[$default];
        if($q!==''){ $like='%'.$q.'%'; $where[]='(u.name LIKE ? OR u.username LIKE ? OR EXISTS(SELECT 1 FROM article_translations tx WHERE tx.article_id=a.id AND tx.title LIKE ?))'; array_push($params,$like,$like,$like); }
        if($category>0){$where[]='a.category_id=?';$params[]=$category;}
        if($author>0){$where[]='a.author_id=?';$params[]=$author;}
        if(in_array($status,['draft','review','changes','published','archived'],true)){$where[]='a.status=?';$params[]=$status;}
        $order=match($sort){'date_asc'=>'COALESCE(a.published_at,a.created_at) ASC,a.id ASC','name_asc'=>'title ASC','name_desc'=>'title DESC','views_desc'=>'a.views DESC,a.id DESC',default=>'COALESCE(a.published_at,a.updated_at,a.created_at) DESC,a.id DESC'};
        $sql="SELECT a.*,u.name author_name,u.username author_username,(SELECT title FROM article_translations t WHERE t.article_id=a.id ORDER BY (t.locale=?) DESC,t.id LIMIT 1) title,(SELECT COUNT(*) FROM article_translations t WHERE t.article_id=a.id) translations FROM articles a JOIN users u ON u.id=a.author_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY {$order} LIMIT 250";
        $articles=Database::all($sql,$params);
        $authors=Database::all("SELECT id,name,username FROM users WHERE role IN ('writer','trusted_writer','editor','admin') ORDER BY name");
        $categories=Database::all("SELECT c.id,COALESCE((SELECT name FROM category_translations ct WHERE ct.category_id=c.id AND ct.locale=? LIMIT 1),(SELECT name FROM category_translations ct2 WHERE ct2.category_id=c.id ORDER BY ct2.id LIMIT 1)) name FROM categories c ORDER BY c.sort_order,c.id",[$default]);
        $filters=compact('q','category','author','status','sort');
        View::render('admin/articles',compact('articles','authors','categories','filters')+['title'=>'المقالات'],'dashboard');
    }
    public function editArticle(string $id): void { $this->contentGuard(); redirect('/writer/articles/'.(int)$id); }
    public function reviewArticle(): void {
        $this->contentGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??$_POST['article_id']??0);
        $action=(string)($_POST['action']??'');
        $note=trim((string)($_POST['review_note']??''));
        $status=match($action){'publish'=>'published','changes'=>'changes','archive'=>'archived',default=>'review'};
        $article=Database::one("SELECT a.id,a.author_id,a.original_locale,u.name author_name,u.email author_email FROM articles a JOIN users u ON u.id=a.author_id WHERE a.id=?",[$id]);
        if(!$article){ flash('error','المقال غير موجود.'); redirect('/admin/articles'); }
        $originalLocale=(string)($article['original_locale']??'ar');
        if(!isset(article_locales()[$originalLocale])) $originalLocale='ar';
        if($status==='published' && $originalLocale!=='ar'){
            Database::exec("UPDATE article_translations SET review_state='approved' WHERE article_id=? AND locale=?",[$id,$originalLocale]);
            if(!article_publication_ready($id)){
                Database::exec("UPDATE articles SET status='review',published_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$id]);
                try{Database::insert('INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)',[(int)$article['author_id'],'article_translation_pending','تم اعتماد العمل تحريرياً','تم اعتماد النص الأصلي، وهو الآن بانتظار الترجمة العربية ومراجعتها قبل النشر.','/writer/articles/'.$id]);}catch(\Throwable){}
                audit_log('article_translation_pending','article',$id,$originalLocale);
                flash('success','تم اعتماد النص الأصلي تحريرياً. بقيت المقالة خاصة بانتظار الترجمة العربية المعتمدة.');
                redirect('/admin/articles');
            }
        }
        Database::exec('UPDATE articles SET status=?,published_at=CASE WHEN ?="published" THEN COALESCE(published_at,CURRENT_TIMESTAMP) ELSE published_at END,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$status,$id]);
        if($status==='published') Database::exec("UPDATE article_translations SET status='published',review_state='approved' WHERE article_id=? AND locale IN (?, 'ar') AND TRIM(title)<>'' AND TRIM(slug)<>'' AND TRIM(content)<>''",[$id,$originalLocale]);
        if($status==='changes') Database::exec("UPDATE article_translations SET status='draft' WHERE article_id=?",[$id]);

        $title=$status==='published'?'تم نشر مقالتك':'مطلوب مراجعة المقال';
        $message=$status==='published'?'وافقت الإدارة على المقال وتم نشر الترجمات الجاهزة فقط.':($note!==''?$note:'طلبت الإدارة بعض التعديلات قبل النشر.');
        try{
            Database::insert('INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)',[(int)$article['author_id'],$status==='published'?'article_published':'article_changes',$title,$message,'/writer/articles/'.$id]);
        }catch(\Throwable){}
        if(in_array($status,['published','changes'],true)){
            send_site_mail((string)$article['author_email'],$title,"مرحبًا {$article['author_name']}،\n\n{$message}\n\n".url('/writer/articles/'.$id));
        }
        flash('success',$status==='published'?'تم نشر المقال والترجمات المكتملة.':'تم إرسال طلب المراجعة للكاتب داخل حسابه وبالبريد عند تفعيل البريد.');
        redirect('/admin/articles');
    }

    public function podcast(): void {
        $this->contentGuard();
        $default=default_locale();
        $q=trim((string)($_GET['q']??''));
        $category=(int)($_GET['category']??0);
        $status=(string)($_GET['status']??'');
        $sort=(string)($_GET['sort']??'date_desc');
        $where=[]; $params=[$default];
        if($q!==''){ $like='%'.$q.'%'; $where[]='(p.title LIKE ? OR p.excerpt LIKE ? OR p.slug LIKE ?)'; array_push($params,$like,$like,$like); }
        if($category>0){ $where[]='p.category_id=?'; $params[]=$category; }
        if(in_array($status,['draft','published'],true)){ $where[]='p.status=?'; $params[]=$status; }
        $order=match($sort){'date_asc'=>'COALESCE(p.published_at,p.created_at) ASC,p.id ASC','episode_desc'=>'p.episode_number DESC,p.id DESC','episode_asc'=>'p.episode_number ASC,p.id ASC','name_asc'=>'p.title ASC','name_desc'=>'p.title DESC',default=>'COALESCE(p.published_at,p.updated_at,p.created_at) DESC,p.id DESC'};
        $sql="SELECT p.*,ct.name category_name,t.title article_title,t.slug article_slug FROM podcast_episodes p LEFT JOIN category_translations ct ON ct.category_id=p.category_id AND ct.locale=? LEFT JOIN article_translations t ON t.article_id=p.article_id AND t.locale=?".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY {$order} LIMIT 250";
        $episodes=Database::all($sql,array_merge([$default],$params));
        $categories=Database::all("SELECT c.id,COALESCE((SELECT name FROM category_translations ct WHERE ct.category_id=c.id AND ct.locale=? LIMIT 1),(SELECT name FROM category_translations ct2 WHERE ct2.category_id=c.id ORDER BY ct2.id LIMIT 1)) name FROM categories c ORDER BY c.sort_order,c.id",[$default]);
        $filters=compact('q','category','status','sort');
        View::render('admin/podcast',compact('episodes','categories','filters')+['title'=>'البودكاست'],'dashboard');
    }

    public function editPodcast(?string $id=null): void {
        $this->contentGuard();
        $episode=null; $byLocale=[];
        if($id && $id!=='new') $episode=Database::one('SELECT * FROM podcast_episodes WHERE id=?',[(int)$id]);
        if($id && $id!=='new' && !$episode){ http_response_code(404); exit('Podcast episode not found'); }
        if($episode) foreach(Database::all('SELECT * FROM podcast_episode_translations WHERE episode_id=?',[(int)$episode['id']]) as $translation) $byLocale[$translation['locale']]=$translation;
        $default=default_locale();
        $categories=Database::all("SELECT c.id,COALESCE((SELECT name FROM category_translations ct WHERE ct.category_id=c.id AND ct.locale=? LIMIT 1),(SELECT name FROM category_translations ct2 WHERE ct2.category_id=c.id ORDER BY ct2.id LIMIT 1)) name FROM categories c ORDER BY c.sort_order,c.id",[$default]);
        $articles=Database::all("SELECT a.id,COALESCE((SELECT title FROM article_translations t WHERE t.article_id=a.id AND t.locale=? LIMIT 1),(SELECT title FROM article_translations tx WHERE tx.article_id=a.id ORDER BY tx.id LIMIT 1)) title FROM articles a ORDER BY a.updated_at DESC LIMIT 300",[$default]);
        $mediaLibrary=[]; try{$mediaLibrary=Database::all("SELECT id,path,title,alt_text,original_name,width,height FROM media WHERE mime LIKE 'image/%' ORDER BY id DESC LIMIT 160");}catch(\Throwable){}
        View::render('admin/podcast-editor',compact('episode','byLocale','categories','articles','mediaLibrary')+['title'=>$episode?'تعديل حلقة بودكاست':'حلقة بودكاست جديدة'],'dashboard');
    }

    public function savePodcast(): void {
        $u=$this->contentGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0);
        $sourceLocale=default_locale();
        $title=mb_substr(trim((string)($_POST['title'][$sourceLocale]??$_POST['title']??'')),0,220);
        $slug=slugify((string)($_POST['slug'][$sourceLocale]??$_POST['slug']??$title));
        $excerpt=trim((string)($_POST['excerpt'][$sourceLocale]??$_POST['excerpt']??''));
        $content=sanitize_html((string)($_POST['content'][$sourceLocale]??$_POST['content']??''));
        $cover=$this->safeMediaPath((string)($_POST['cover_image']??''));
        $og=$this->safeMediaPath((string)($_POST['og_image']??'')) ?: $cover;
        $episodeNumber=max(0,(int)($_POST['episode_number']??0)) ?: null;
        if(!$episodeNumber){
            $next=Database::one('SELECT COALESCE(MAX(episode_number),0)+1 next_number FROM podcast_episodes WHERE id<>?',[$id]);
            $episodeNumber=max(1,(int)($next['next_number']??1));
        }
        $duration=mb_substr(trim((string)($_POST['duration']??'')),0,40);
        $categoryId=max(0,(int)($_POST['category_id']??0)) ?: null;
        $articleId=max(0,(int)($_POST['article_id']??0)) ?: null;
        $status=in_array($_POST['status']??'draft',['draft','published'],true)?(string)$_POST['status']:'draft';
        $publishedAt=trim((string)($_POST['published_at']??''));
        $publishedAt=$publishedAt!==''?str_replace('T',' ',$publishedAt):null;
        $seoTitle=mb_substr(trim((string)($_POST['seo_title'][$sourceLocale]??$_POST['seo_title']??'')),0,255);
        $seoDesc=mb_substr(trim((string)($_POST['seo_description'][$sourceLocale]??$_POST['seo_description']??'')),0,320);
        $audioSource=in_array($_POST['audio_source']??'external',['external','upload'],true)?(string)$_POST['audio_source']:'external';
        $audioUrl=trim((string)($_POST['audio_url']??''));
        $sourceType=in_array($_POST['source_type']??'', ['auto','direct','youtube','rss','cdn'],true)?(string)$_POST['source_type']:'auto';
        $coverTemplate='cinematic';
        if(!empty($_FILES['audio_file']['name'])){
            $file=Upload::fileDetailed($_FILES['audio_file']);
            if(!$file || ($file['kind']??'')!=='audio'){ flash('error','ملف الصوت غير صالح.'); redirect($id?'/admin/podcast/'.$id:'/admin/podcast/new'); }
            $audioUrl=(string)$file['path']; $audioSource='upload';
            $sourceType='direct';
        }
        if($title==='' || $audioUrl==='' || !$this->validAudioUrl($audioUrl)){
            flash('error','تحقق من عنوان الحلقة ورابط الصوت. يسمح بروابط http/https أو ملف صوت مرفوع فقط.');
            redirect($id?'/admin/podcast/'.$id:'/admin/podcast/new');
        }
        $detected=podcast_source_type($audioUrl); if($sourceType==='auto')$sourceType=$detected;
        if($sourceType==='youtube' && podcast_youtube_id($audioUrl)===''){flash('error','رابط YouTube غير صالح أو غير مدعوم.');redirect($id?'/admin/podcast/'.$id:'/admin/podcast/new');}
        $duplicate=Database::one('SELECT id FROM podcast_episodes WHERE slug=? AND id<>? LIMIT 1',[$slug,$id]);
        if($duplicate){ flash('error','Slug مستخدم في حلقة أخرى.'); redirect($id?'/admin/podcast/'.$id:'/admin/podcast/new'); }
        if($articleId && !Database::one('SELECT id FROM articles WHERE id=?',[$articleId])) $articleId=null;
        if($categoryId && !Database::one('SELECT id FROM categories WHERE id=?',[$categoryId])) $categoryId=null;
        if($status==='published' && !$publishedAt) $publishedAt=date('Y-m-d H:i:s');
        if($id){
            Database::exec('UPDATE podcast_episodes SET article_id=?,category_id=?,title=?,slug=?,excerpt=?,content=?,cover_image=?,cover_template=?,cover_template_version=1,og_image=?,episode_number=?,duration=?,audio_source=?,source_type=?,audio_url=?,status=?,published_at=?,seo_title=?,seo_description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$articleId,$categoryId,$title,$slug,$excerpt,$content,$cover,$coverTemplate,$og,$episodeNumber,$duration,$audioSource,$sourceType,$audioUrl,$status,$publishedAt,$seoTitle,$seoDesc,$id]);
        } else {
            $id=Database::insert('INSERT INTO podcast_episodes(article_id,category_id,created_by,title,slug,excerpt,content,cover_image,cover_template,cover_template_version,og_image,episode_number,duration,audio_source,source_type,audio_url,status,published_at,seo_title,seo_description) VALUES(?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,?)',[$articleId,$categoryId,(int)$u['id'],$title,$slug,$excerpt,$content,$cover,$coverTemplate,$og,$episodeNumber,$duration,$audioSource,$sourceType,$audioUrl,$status,$publishedAt,$seoTitle,$seoDesc]);
        }
        foreach(enabled_locales() as $locale=>$language){
            $localizedTitle=mb_substr(trim((string)($_POST['title'][$locale]??'')),0,220);
            $localizedContent=sanitize_html((string)($_POST['content'][$locale]??''));
            if($localizedTitle==='' && trim(strip_tags($localizedContent))==='') continue;
            $localizedSlug=slugify((string)($_POST['slug'][$locale]??$localizedTitle));
            $localizedExcerpt=trim((string)($_POST['excerpt'][$locale]??''));
            $localizedSeoTitle=mb_substr(trim((string)($_POST['seo_title'][$locale]??'')),0,255);
            $localizedSeoDescription=mb_substr(trim((string)($_POST['seo_description'][$locale]??'')),0,320);
            $localizedStatus=(string)($_POST['translation_status'][$locale]??'draft');
            if(!in_array($localizedStatus,['draft','review','published'],true)) $localizedStatus='draft';
            $old=Database::one('SELECT slug FROM podcast_episode_translations WHERE episode_id=? AND locale=?',[$id,$locale]);
            if($old && (string)$old['slug']!==$localizedSlug && (string)$old['slug']!==''){
                $oldPath=parse_url(locale_url($locale,'podcast/'.$old['slug']),PHP_URL_PATH)?:'/';
                $newPath=parse_url(locale_url($locale,'podcast/'.$localizedSlug),PHP_URL_PATH)?:'/';
                Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),http_code=301',[$oldPath,$newPath]);
            }
            Database::exec('INSERT INTO podcast_episode_translations(episode_id,locale,title,slug,excerpt,content,seo_title,seo_description,og_title,og_description,status) VALUES(?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),slug=VALUES(slug),excerpt=VALUES(excerpt),content=VALUES(content),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),og_title=VALUES(og_title),og_description=VALUES(og_description),status=VALUES(status),updated_at=CURRENT_TIMESTAMP',[$id,$locale,$localizedTitle,$localizedSlug,$localizedExcerpt,$localizedContent,$localizedSeoTitle,$localizedSeoDescription,$localizedTitle,$localizedSeoDescription,$localizedStatus]);
        }
        flash('success','تم حفظ حلقة البودكاست.');
        redirect('/admin/podcast/'.$id);
    }

    public function deletePodcast(): void {
        $this->contentGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0);
        Database::exec('DELETE FROM podcast_episodes WHERE id=?',[$id]);
        flash('success','تم حذف الحلقة.');
        redirect('/admin/podcast');
    }

    private function safeMediaPath(string $value): string {
        $value=trim($value);
        if($value==='' || (!str_starts_with($value,'/') && !preg_match('#^https?://#i',$value))) return '';
        if(preg_match('/[<>"\']|javascript:|data:/i',$value)) return '';
        return mb_substr($value,0,500);
    }

    private function validAudioUrl(string $value): bool {
        $value=trim($value);
        if($value==='' || preg_match('/[<>"\']|javascript:|data:|file:|php:|<script/i',$value)) return false;
        if(str_starts_with($value,'/')) return (bool)preg_match('/\.(mp3|m4a|wav|ogg)(?:\?.*)?$/i',$value);
        if(!filter_var($value,FILTER_VALIDATE_URL) || preg_match('/\.(exe|msi|bat|cmd|com|php|phar|sh)(?:$|\?)/i',$value)) return false;
        $scheme=parse_url($value,PHP_URL_SCHEME);
        return in_array(strtolower((string)$scheme),['http','https'],true);
    }

    public function pages(): void {
        $this->adminGuard();
        $default=default_locale();
        $pages=Database::all("SELECT p.*, (SELECT title FROM page_translations pt WHERE pt.page_id=p.id AND pt.locale=? LIMIT 1) title,(SELECT slug FROM page_translations pt WHERE pt.page_id=p.id AND pt.locale=? LIMIT 1) slug,(SELECT COUNT(*) FROM page_translations pt WHERE pt.page_id=p.id AND pt.status='published' AND pt.review_state='approved' AND TRIM(pt.title)<>'' AND TRIM(pt.slug)<>'') translations FROM pages p WHERE p.deleted_at IS NULL ORDER BY p.sort_order,p.updated_at DESC",[$default,$default]);
        $trashedPages=Database::all("SELECT p.*, (SELECT title FROM page_translations pt WHERE pt.page_id=p.id AND pt.locale=? LIMIT 1) title FROM pages p WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC",[$default]);
        $footerKeys=json_decode(setting('footer_menu_json',''),true); if(!is_array($footerKeys)) $footerKeys=[];
        foreach($pages as &$managedPage) $managedPage['in_footer']=in_array('page:'.(int)$managedPage['id'],$footerKeys,true); unset($managedPage);
        $systemPages=[['name'=>'تسجيل الدخول','route'=>'/login'],['name'=>'إنشاء حساب','route'=>'/register'],['name'=>'استعادة كلمة المرور','route'=>'/forgot-password'],['name'=>'البحث','route'=>'/search'],['name'=>'ملف الكاتب','route'=>'/writers/{username}'],['name'=>'المقال','route'=>'/article/{slug}'],['name'=>'التصنيف','route'=>'/category/{slug}'],['name'=>'404','route'=>'/أي-رابط-غير-موجود']];
        View::render('admin/pages',compact('pages','trashedPages')+['title'=>'الصفحات'],'dashboard');
    }
    public function editPage(?string $id=null): void {
        $this->adminGuard(); $page=null; $byLocale=[];
        if($id && $id!=='new'){
            $page=Database::one('SELECT * FROM pages WHERE id=? AND deleted_at IS NULL',[(int)$id]);
            if(!$page){ http_response_code(404); exit('Page not found or in trash.'); }
            foreach(Database::all('SELECT * FROM page_translations WHERE page_id=?',[(int)$id]) as $t)$byLocale[$t['locale']]=$t;
        }
        $mediaLibrary=[]; try{$mediaLibrary=Database::all("SELECT id,path,title,alt_text,original_name,width,height FROM media WHERE mime LIKE 'image/%' ORDER BY id DESC LIMIT 160");}catch(\Throwable){}
        $footerMenu=json_decode(setting('footer_menu_json',''),true); if(!is_array($footerMenu)) $footerMenu=[];
        $showInFooter=$page && in_array('page:'.(int)$page['id'],$footerMenu,true);
        if($page) $page['show_in_footer']=$showInFooter;
        View::render('admin/page-editor',compact('page','byLocale','mediaLibrary')+['title'=>$page?'تعديل الصفحة':'صفحة جديدة'],'dashboard');
    }
    public function savePage(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['page_id']??0);
        $status=in_array($_POST['status']??'draft',['draft','published'],true)?$_POST['status']:'draft';
        $sort=(int)($_POST['sort_order']??0);
        $template=in_array($_POST['template']??'standard',['standard','wide','landing'],true)?$_POST['template']:'standard';
        Database::transaction(function() use(&$id,$status,$sort,$template){
            if(!$id) $id=Database::insert('INSERT INTO pages(status,sort_order,template) VALUES(?,?,?)',[$status,$sort,$template]);
            else Database::exec('UPDATE pages SET status=?,sort_order=?,template=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$sort,$template,$id]);
            foreach(all_locales() as $loc=>$i){
                $title=trim((string)($_POST['title'][$loc]??''));
                $content=(string)($_POST['content'][$loc]??'');
                $mode=in_array($_POST['content_mode'][$loc]??'visual',['visual','html'],true)?$_POST['content_mode'][$loc]:'visual';
                if($title===''&&trim($content)==='') continue;
                $slug=slugify((string)($_POST['slug'][$loc]??$title));
                $seoTitle=trim((string)($_POST['seo_title'][$loc]??''));
                $seoDesc=trim((string)($_POST['seo_description'][$loc]??''));
                $customCss=(string)($_POST['custom_css'][$loc]??'');
                $customJs=(string)($_POST['custom_js'][$loc]??'');
                $featured=trim((string)($_POST['featured_image'][$loc]??'')); if($featured!==''&&!preg_match('#^(?:https?://|/)#i',$featured))$featured='';
                $canonical=trim((string)($_POST['canonical_url'][$loc]??'')); if($canonical!==''&&!preg_match('#^https?://#i',$canonical))$canonical='';
                $showTitle=isset($_POST['show_title'][$loc])?1:0;
                $hex=static fn(string $v,string $fallback): string => preg_match('/^#[0-9a-f]{6}$/i',trim($v))?strtolower(trim($v)):$fallback;
                $pageBg=$hex((string)($_POST['page_bg_color'][$loc]??'#ffffff'),'#ffffff');
                $pageText=$hex((string)($_POST['page_text_color'][$loc]??'#202522'),'#202522');
                $contentWidth=in_array($_POST['content_width'][$loc]??'standard',['narrow','standard','wide','full'],true)?$_POST['content_width'][$loc]:'standard';
                $translationStatus=(string)($_POST['translation_status'][$loc]??'draft'); if(!in_array($translationStatus,['draft','review','published'],true))$translationStatus='draft';
                $reviewState=(string)($_POST['review_state'][$loc]??($translationStatus==='published'?'approved':'draft')); if(!in_array($reviewState,['draft','review','approved'],true))$reviewState='draft';
                $storedContent=$mode==='html'?$content:sanitize_page_html($content);
                $x=Database::one('SELECT id,slug FROM page_translations WHERE page_id=? AND locale=?',[$id,$loc]);
                if($x && (string)($x['slug']??'')!==$slug && (string)($x['slug']??'')!==''){ $oldPath=parse_url(locale_url($loc,'page/'.(string)$x['slug']),PHP_URL_PATH)?:'/'; $newPath=parse_url(locale_url($loc,'page/'.$slug),PHP_URL_PATH)?:'/'; try{Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),http_code=301',[$oldPath,$newPath]);}catch(\Throwable){} }
                if($x) Database::exec('UPDATE page_translations SET title=?,slug=?,content=?,content_mode=?,custom_css=?,custom_js=?,featured_image=?,seo_title=?,seo_description=?,canonical_url=?,show_title=?,page_bg_color=?,page_text_color=?,content_width=?,status=?,review_state=? WHERE id=?',[$title,$slug,$storedContent,$mode,$customCss,$customJs,$featured,$seoTitle,$seoDesc,$canonical,$showTitle,$pageBg,$pageText,$contentWidth,$translationStatus,$reviewState,$x['id']]);
                else Database::insert('INSERT INTO page_translations(page_id,locale,title,slug,content,content_mode,custom_css,custom_js,featured_image,seo_title,seo_description,canonical_url,show_title,page_bg_color,page_text_color,content_width,status,review_state) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$loc,$title,$slug,$storedContent,$mode,$customCss,$customJs,$featured,$seoTitle,$seoDesc,$canonical,$showTitle,$pageBg,$pageText,$contentWidth,$translationStatus,$reviewState]);
            }
        });
        $this->setPageFooterVisibility($id,isset($_POST['show_in_footer']));
        flash('success','تم حفظ الصفحة.'); redirect('/admin/pages/'.$id);
    }

    public function togglePage(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $page=Database::one('SELECT id,status FROM pages WHERE id=? AND deleted_at IS NULL',[$id]);
        if(!$page){ flash('error','الصفحة غير موجودة.'); redirect('/admin/pages'); }
        $next=($page['status']??'draft')==='published'?'draft':'published';
        Database::exec('UPDATE pages SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$next,$id]);
        audit_log('page_toggle','page',$id,$next); flash('success',$next==='published'?'تم تفعيل الصفحة.':'تم تعطيل الصفحة.'); redirect('/admin/pages');
    }

    /** Keeps page placement in the footer menu independent from all other footer links. */
    private function setPageFooterVisibility(int $pageId,bool $visible): void {
        if($pageId<1) return;
        $items=json_decode(setting('footer_menu_json',''),true);
        if(!is_array($items)) $items=['story','explore','community','behind'];
        $key='page:'.$pageId;
        $items=array_values(array_filter($items,static fn($item)=>$item!==$key));
        if($visible) $items[]=$key;
        $this->putSetting('footer_menu_json',json_encode(array_values(array_unique($items)),JSON_UNESCAPED_UNICODE));
    }

    public function togglePageFooter(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0);
        $page=Database::one('SELECT id FROM pages WHERE id=? AND deleted_at IS NULL',[$id]);
        if(!$page){ flash('error','الصفحة غير موجودة.'); redirect('/admin/pages'); }
        $items=json_decode(setting('footer_menu_json',''),true); if(!is_array($items)) $items=[];
        $visible=!in_array('page:'.$id,$items,true);
        $this->setPageFooterVisibility($id,$visible);
        audit_log('page_footer_toggle','page',$id,$visible?'shown':'hidden');
        flash('success',$visible?'أُضيفت الصفحة إلى الفوتر.':'أُزيلت الصفحة من الفوتر.'); redirect('/admin/pages');
    }

    public function trashPage(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0);
        $page=Database::one('SELECT id FROM pages WHERE id=? AND deleted_at IS NULL',[$id]);
        if(!$page){ flash('error','الصفحة غير موجودة.'); redirect('/admin/pages'); }
        Database::exec("UPDATE pages SET status='draft',deleted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$id]);
        $this->setPageFooterVisibility($id,false);
        audit_log('page_trash','page',$id,'recoverable');
        flash('success','نُقلت الصفحة إلى سلة المهملات وأُزيلت من الفوتر.'); redirect('/admin/pages');
    }

    public function restorePage(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0);
        $page=Database::one('SELECT id FROM pages WHERE id=? AND deleted_at IS NOT NULL',[$id]);
        if(!$page){ flash('error','لا توجد صفحة قابلة للاستعادة.'); redirect('/admin/pages'); }
        Database::exec('UPDATE pages SET deleted_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);
        audit_log('page_restore','page',$id,'restored');
        flash('success','استُعيدت الصفحة. يمكنك نشرها أو إضافتها إلى الفوتر من جديد.'); redirect('/admin/pages');
    }

    public function users(): void {
        $u=$this->writerManagementGuard();
        $fullAdmin=($u['role']??'')==='admin';
        if($fullAdmin){
            $users=Database::all('SELECT id,name,username,email,phone,role,status,bio,writer_verified,writer_honored,created_at,last_login_at FROM users ORDER BY created_at DESC LIMIT 250');
        } else {
            $users=Database::all("SELECT id,name,username,email,phone,role,status,bio,writer_verified,writer_honored,created_at,last_login_at FROM users WHERE role IN ('writer','trusted_writer') ORDER BY created_at DESC LIMIT 250");
        }
        View::render('admin/users',compact('users','fullAdmin')+['title'=>$fullAdmin?'الحسابات والصلاحيات':'إدارة الكتّاب'],'dashboard');
    }

    /** Dedicated writer-account control centre, kept separate from general users. */
    public function writers(): void {
        $actor=$this->writerManagementGuard();
        $q=trim((string)($_GET['q']??''));
        $status=(string)($_GET['status']??'');
        $where=["u.role IN ('writer','trusted_writer')"]; $params=[];
        if($q!==''){$where[]='(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
        if(in_array($status,['active','pending','blocked'],true)){$where[]='u.status=?';$params[]=$status;}
        $writers=Database::all("SELECT u.id,u.name,u.username,u.email,u.phone,u.avatar,u.role,u.status,u.writer_verified,u.writer_honored,u.created_at,u.last_login_at,p.professional_title,p.location,p.is_public,COUNT(a.id) articles,SUM(a.status='published') published,COALESCE(SUM(a.views),0) views,MAX(a.updated_at) last_article_at FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN articles a ON a.author_id=u.id WHERE ".implode(' AND ',$where).' GROUP BY u.id ORDER BY u.created_at DESC LIMIT 300',$params);
        $stats=['total'=>count($writers),'active'=>0,'pending'=>0,'blocked'=>0]; foreach($writers as $w){$key=(string)$w['status'];if(isset($stats[$key]))$stats[$key]++;}
        $fullAdmin=($actor['role']??'')==='admin';
        View::render('admin/writers',compact('writers','stats','q','status','fullAdmin')+['title'=>'حسابات الكتّاب'],'dashboard');
    }

    /** Updates writer-only account and public professional-profile controls. */
    public function saveWriter(): void {
        $actor=$this->writerManagementGuard(); Csrf::enforce(); $fullAdmin=($actor['role']??'')==='admin';
        $id=(int)($_POST['id']??0); $writer=Database::one("SELECT u.*,p.user_id profile_exists FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id WHERE u.id=? AND u.role IN ('writer','trusted_writer')",[$id]);
        if(!$writer){flash('error','حساب الكاتب غير موجود.');redirect('/admin/writers');}
        $name=mb_substr(trim((string)($_POST['name']??'')),0,190); $email=mb_strtolower(trim((string)($_POST['email']??''))); $phone=mb_substr(trim((string)($_POST['phone']??'')),0,80);
        $username=normalize_writer_username((string)($_POST['username']??'')); $role=(string)($_POST['role']??'writer'); $status=(string)($_POST['status']??'active');
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!valid_writer_username($username)||!in_array($role,['writer','trusted_writer'],true)||!in_array($status,['active','pending','blocked'],true)){flash('error','تحقق من الاسم والبريد واسم المستخدم والدور والحالة.');redirect('/admin/writers/'.$id);}
        $duplicate=Database::one('SELECT id FROM users WHERE (email=? OR username=?) AND id<>? LIMIT 1',[$email,$username,$id]);
        try{$alias=Database::one('SELECT user_id FROM writer_username_redirects WHERE old_username=? AND user_id<>? LIMIT 1',[$username,$id]);}catch(\Throwable){$alias=null;}
        if($duplicate||$alias){flash('error','البريد أو اسم المستخدم مستخدم بالفعل.');redirect('/admin/writers/'.$id);}
        $password=(string)($_POST['new_password']??''); if($password!==''&&mb_strlen($password)<12){flash('error','كلمة المرور الجديدة يجب ألا تقل عن 12 محرفًا.');redirect('/admin/writers/'.$id);}
        $verified=$fullAdmin?isset($_POST['writer_verified']):(int)$writer['writer_verified']; $honored=$fullAdmin?isset($_POST['writer_honored']):(int)$writer['writer_honored']; $vip=$fullAdmin?isset($_POST['writer_vip']):(int)($writer['writer_vip']??0);
        if($username!==$writer['username']){try{Database::insert('INSERT IGNORE INTO writer_username_redirects(user_id,old_username) VALUES(?,?)',[$id,$writer['username']]);}catch(\Throwable){}}
        $avatar=(string)($writer['avatar']??'');
        if(!empty($_FILES['avatar']['tmp_name'])){try{$uploaded=Upload::image($_FILES['avatar']);if($uploaded)$avatar=$uploaded;}catch(\Throwable $e){flash('error',$e->getMessage());redirect('/admin/writers/'.$id);}}
        Database::exec('UPDATE users SET name=?,username=?,email=?,phone=?,bio=?,role=?,status=?,avatar=?,writer_verified=?,writer_honored=?,writer_vip=? WHERE id=?',[$name,$username,$email,$phone,mb_substr(trim((string)($_POST['bio']??'')),0,1200),$role,$status,$avatar,$verified?1:0,$honored?1:0,$vip?1:0,$id]);
        if($password!==''){ $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT; Database::exec('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,$algo),$id]); }
        $cleanUrl=static fn(string $v):string=>($v=trim($v))===''||filter_var($v,FILTER_VALIDATE_URL)?$v:'';
        $title=mb_substr(trim((string)($_POST['professional_title']??'')),0,190); $summary=mb_substr(trim((string)($_POST['cv_summary']??'')),0,5000); $location=mb_substr(trim((string)($_POST['location']??'')),0,190); $contact=trim((string)($_POST['contact_email']??'')); if($contact!==''&&!filter_var($contact,FILTER_VALIDATE_EMAIL))$contact='';
        Database::exec('INSERT INTO writer_profiles(user_id,professional_title,cv_summary,location,website,linkedin,portfolio_url,contact_email,is_public,show_contact) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE professional_title=VALUES(professional_title),cv_summary=VALUES(cv_summary),location=VALUES(location),website=VALUES(website),linkedin=VALUES(linkedin),portfolio_url=VALUES(portfolio_url),contact_email=VALUES(contact_email),is_public=VALUES(is_public),show_contact=VALUES(show_contact)',[$id,$title,$summary,$location,$cleanUrl((string)($_POST['website']??'')),$cleanUrl((string)($_POST['linkedin']??'')),$cleanUrl((string)($_POST['portfolio_url']??'')),$contact,isset($_POST['is_public'])?1:0,isset($_POST['show_contact'])?1:0]);
        audit_log('writer_account_update','user',$id,'writer management'); flash('success','تم حفظ حساب الكاتب وملفه المهني.'); redirect('/admin/writers/'.$id);
    }
    public function saveUser(): void {
        $actor=$this->writerManagementGuard(); Csrf::enforce();
        $fullAdmin=($actor['role']??'')==='admin';
        $id=(int)($_POST['id']??0);
        $name=trim((string)($_POST['name']??''));
        $email=mb_strtolower(trim((string)($_POST['email']??''))); $phone=trim((string)($_POST['phone']??'')); $bio=trim((string)($_POST['bio']??''));
        $role=(string)($_POST['role']??'writer'); $status=(string)($_POST['status']??'active');
        $rawUsername=trim((string)($_POST['username']??''));
        $username=in_array($role,['writer','trusted_writer'],true)?normalize_writer_username($rawUsername):slugify($rawUsername);
        $allowedRoles=$fullAdmin?['member','writer','trusted_writer','editor','admin']:['writer','trusted_writer'];
        $statuses=['active','pending','blocked'];
        $writerUsernameInvalid=in_array($role,['writer','trusted_writer'],true) && !valid_writer_username($username);
        if(!in_array($role,$allowedRoles,true)||!in_array($status,$statuses,true)||$name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$writerUsernameInvalid){
            flash('error',$writerUsernameInvalid?'اسم مستخدم الكاتب يجب أن يكون إنجليزيًا وفريدًا من 3 إلى 30 محرفًا.':'تحقق من بيانات الحساب والصلاحية.'); redirect('/admin/users');
        }
        $existing=$id?Database::one('SELECT id,role FROM users WHERE id=?',[$id]):null;
        if($id && !$existing){ flash('error','الحساب غير موجود.'); redirect('/admin/users'); }
        if(!$fullAdmin && $existing && !in_array($existing['role'],['writer','trusted_writer'],true)){ http_response_code(403); exit('لا يملك مساعد المدير صلاحية تعديل حسابات الإدارة.'); }
        if($fullAdmin && $id===(int)$actor['id'] && ($role!=='admin'||$status!=='active')){ flash('error','لا يمكنك إزالة صلاحية المدير الكامل أو حظر حسابك الحالي.'); redirect('/admin/users'); }
        $duplicate=Database::one('SELECT id FROM users WHERE (email=? OR username=?) AND id<>? LIMIT 1',[$email,$username,$id]); $aliasDuplicate=null; if(in_array($role,['writer','trusted_writer'],true)){try{$aliasDuplicate=Database::one('SELECT user_id FROM writer_username_redirects WHERE old_username=? AND user_id<>? LIMIT 1',[$username,$id]);}catch(\Throwable){}}
        if($duplicate||$aliasDuplicate){ flash('error','البريد أو اسم المستخدم مستخدم بالفعل.'); redirect('/admin/users'); }
        $password=(string)($_POST['new_password']??'');
        if($password!=='' && mb_strlen($password)<12){ flash('error','كلمة المرور الجديدة يجب ألا تقل عن 12 محرفًا.'); redirect('/admin/users'); }
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;

        if(!$id){
            if(in_array($role,['writer','trusted_writer'],true) && $password===''){
                $token=bin2hex(random_bytes(32));
                $random=password_hash(bin2hex(random_bytes(24)),$algo);
                $id=Database::insert("INSERT INTO users(name,username,email,phone,password_hash,role,status,bio,activation_token_hash,activation_expires_at) VALUES(?,?,?,?,?,?,'pending',?,?,?)",[$name,$username,$email,$phone,$random,$role,$bio,hash('sha256',$token),date('Y-m-d H:i:s',time()+172800)]);
                $link=url('/activate').'?token='.rawurlencode($token);
                $sent=send_site_mail($email,'تفعيل حساب الكاتب في سراديب',"مرحبًا {$name}،\n\nتم قبولك ككاتب. أكد بريدك وأنشئ كلمة المرور من الرابط التالي خلال 48 ساعة:\n{$link}");
                flash('success','تم إنشاء الحساب بانتظار تأكيد البريد.'.((!$sent&&config('debug',false))?' رابط التجربة المحلية: '.$link:''));
            }else{
                if(mb_strlen($password)<12){ flash('error','حدد كلمة مرور من 12 محرفًا على الأقل لهذا الحساب.'); redirect('/admin/users'); }
                $hash=password_hash($password,$algo);
                $verified=$fullAdmin&&isset($_POST['writer_verified'])?1:0; $honored=$fullAdmin&&isset($_POST['writer_honored'])?1:0;
                Database::insert('INSERT INTO users(name,username,email,phone,password_hash,role,status,bio,writer_verified,writer_honored,email_verified_at) VALUES(?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',[$name,$username,$email,$phone,$hash,$role,$status,$bio,$verified,$honored]);
                flash('success',$role==='editor'?'تم إنشاء حساب مساعد المدير بصلاحيات محدودة.':'تم إنشاء الحساب.');
            }
        }else{
            $oldUsername=(string)(Database::one('SELECT username FROM users WHERE id=?',[$id])['username']??'');
            if($oldUsername!=='' && $oldUsername!==$username){ try{Database::insert('INSERT IGNORE INTO writer_username_redirects(user_id,old_username) VALUES(?,?)',[$id,$oldUsername]);}catch(\Throwable){} }
            $verified=$fullAdmin&&isset($_POST['writer_verified'])?1:(int)(Database::one('SELECT writer_verified FROM users WHERE id=?',[$id])['writer_verified']??0);
            $honored=$fullAdmin&&isset($_POST['writer_honored'])?1:($fullAdmin?0:(int)(Database::one('SELECT writer_honored FROM users WHERE id=?',[$id])['writer_honored']??0));
            Database::exec('UPDATE users SET name=?,username=?,email=?,phone=?,bio=?,role=?,status=?,writer_verified=?,writer_honored=? WHERE id=?',[$name,$username,$email,$phone,$bio,$role,$status,$verified,$honored,$id]);
            if($password!=='') Database::exec('UPDATE users SET password_hash=? WHERE id=?',[password_hash($password,$algo),$id]);
            audit_log('user_update','user',$id,$role); flash('success','تم تحديث بيانات الحساب.');
        }
        redirect('/admin/users');
    }

    public function writerApplications(): void {
        $this->writerManagementGuard();
        $applications=Database::all("SELECT wa.*,u.name AS account_name,u.username AS account_username,u.role AS account_role,u.status AS account_status FROM writer_applications wa LEFT JOIN users u ON u.id=wa.user_id ORDER BY wa.created_at DESC LIMIT 300");
        View::render('admin/writer-applications',compact('applications')+['title'=>'طلبات الانضمام ككاتب'],'dashboard');
    }
    public function updateWriterApplication(): void {
        $this->writerManagementGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??'reviewed');
        if(!in_array($status,['pending','under_review','changes_requested','approved','rejected','suspended','new','reviewed'],true)) $status='under_review';
        $application=Database::one('SELECT id,user_id,status FROM writer_applications WHERE id=?',[$id]);
        if(!$application){ flash('error','الطلب غير موجود.'); redirect('/admin/writer-applications'); }
        $note=trim((string)($_POST['admin_note']??''));
        Database::transaction(function() use($application,$id,$status,$note){
            Database::exec('UPDATE writer_applications SET status=?,admin_note=? WHERE id=?',[$status,$note,$id]);
            if(!empty($application['user_id'])){
                if($status==='approved') Database::exec("UPDATE users SET role='writer',status='active' WHERE id=?",[(int)$application['user_id']]);
                if($status==='rejected') Database::exec("UPDATE users SET role='member',status='active' WHERE id=?",[(int)$application['user_id']]);
                if($status==='suspended') Database::exec("UPDATE users SET status='blocked' WHERE id=?",[(int)$application['user_id']]);
            }
            try { Database::insert('INSERT INTO writer_application_history(application_id,from_status,to_status,admin_id,note) VALUES(?,?,?,?,?)',[$id,(string)$application['status'],$status,(int)(Auth::user()['id']??0),$note]); } catch(\Throwable) {}
        });
        audit_log('writer_application_update','writer_application',$id,$status); flash('success','تم تحديث الطلب.'); redirect('/admin/writer-applications');
    }
    public function createWriterFromApplication(): void {
        $this->writerManagementGuard(); Csrf::enforce();
        $linked=Database::one('SELECT id,user_id FROM writer_applications WHERE id=?',[(int)($_POST['id']??0)]);
        if($linked && !empty($linked['user_id'])){
            Database::exec("UPDATE users SET role='writer',status='active' WHERE id=?",[(int)$linked['user_id']]);
            Database::exec("UPDATE writer_applications SET status='approved' WHERE id=?",[(int)$linked['id']]);
            flash('success','تم اعتماد الحساب الحالي ككاتب؛ لا يلزم إنشاء حساب أو كلمة مرور جديدة.');
            redirect('/admin/writer-applications');
        }
        $id=(int)($_POST['id']??0); $app=Database::one('SELECT * FROM writer_applications WHERE id=?',[$id]);
        if(!$app){ flash('error','الطلب غير موجود.'); redirect('/admin/writer-applications'); }
        if(empty($app['email_verified_at'])){ flash('error','يجب تأكيد بريد صاحب الطلب أولًا.'); redirect('/admin/writer-applications'); }
        $username=normalize_writer_username((string)($_POST['username']??''));
        if(!valid_writer_username($username)){ flash('error','اختر اسم مستخدم إنجليزيًا صالحًا من 3 إلى 30 محرفًا.'); redirect('/admin/writer-applications'); }
        if(Database::one('SELECT id FROM users WHERE email=? OR username=?',[$app['email'],$username]) || (schema_table_exists('writer_username_redirects') && Database::one('SELECT user_id FROM writer_username_redirects WHERE old_username=?',[$username]))){ flash('error','يوجد حساب بنفس البريد أو اسم المستخدم، أو أن الاسم مستخدم كرابط قديم.'); redirect('/admin/writer-applications'); }
        $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
        $token=bin2hex(random_bytes(32));
        $randomHash=password_hash(bin2hex(random_bytes(24)),$algo);
        Database::insert("INSERT INTO users(name,username,email,phone,password_hash,role,status,bio,activation_token_hash,activation_expires_at) VALUES(?,?,?,?,?,'writer','pending',?,?,?)",[$app['name'],$username,$app['email'],$app['phone'],$randomHash,$app['bio'],hash('sha256',$token),date('Y-m-d H:i:s',time()+172800)]);
        Database::exec("UPDATE writer_applications SET status='approved',admin_note=? WHERE id=?",['تم إنشاء الحساب وأُرسل رابط تأكيد البريد وإنشاء كلمة المرور.',$id]);
        $link=url('/activate').'?token='.rawurlencode($token);
        $sent=send_site_mail($app['email'],'تفعيل حساب الكاتب في سراديب',"مرحبًا {$app['name']}،\n\nتم قبول طلبك. أكد بريدك وأنشئ كلمة المرور خلال 48 ساعة:\n{$link}");
        flash('success','تم إنشاء حساب الكاتب وإرسال رابط التفعيل.'.((!$sent&&config('debug',false))?' رابط التجربة المحلية: '.$link:''));
        redirect('/admin/writer-applications');
    }

    public function categories(): void {
        $this->adminGuard();
        $default=default_locale(); $categories=Database::all("SELECT c.*, (SELECT name FROM category_translations ct WHERE ct.category_id=c.id AND ct.locale=? LIMIT 1) ar_name FROM categories c ORDER BY sort_order,id",[$default]);
        $editId=(int)($_GET['edit']??0); $editCategory=$editId?Database::one('SELECT * FROM categories WHERE id=?',[$editId]):null; $categoryByLocale=[];
        if($editCategory) foreach(Database::all('SELECT * FROM category_translations WHERE category_id=?',[$editId]) as $translation)$categoryByLocale[$translation['locale']]=$translation;
        $tags=Database::all("SELECT g.*,COALESCE((SELECT name FROM tag_translations t WHERE t.tag_id=g.id AND t.locale=? LIMIT 1),g.name) display_name,(SELECT COUNT(*) FROM tag_translations tx WHERE tx.tag_id=g.id) translation_count FROM tags g ORDER BY display_name",[$default]);
        View::render('admin/categories',compact('categories','editCategory','categoryByLocale','tags')+['title'=>'التصنيفات'],'dashboard');
    }
    public function saveCategory(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $active=isset($_POST['active'])?1:0; if(!$id)$id=Database::insert('INSERT INTO categories(sort_order,active) VALUES(?,?)',[(int)($_POST['sort_order']??0),$active]); else Database::exec('UPDATE categories SET sort_order=?,active=? WHERE id=?',[(int)($_POST['sort_order']??0),$active,$id]);
        foreach(all_locales() as $loc=>$i){$name=trim((string)($_POST['name'][$loc]??'')); if($name==='')continue; $slug=slugify((string)($_POST['slug'][$loc]??$name)); $desc=trim((string)($_POST['description'][$loc]??''));$seoTitle=mb_substr(trim((string)($_POST['seo_title'][$loc]??'')),0,255);$seoDescription=mb_substr(trim((string)($_POST['seo_description'][$loc]??'')),0,320);$translationStatus=(string)($_POST['translation_status'][$loc]??'draft');if(!in_array($translationStatus,['draft','review','published'],true))$translationStatus='draft'; $x=Database::one('SELECT id,slug FROM category_translations WHERE category_id=? AND locale=?',[$id,$loc]); if($x && (string)($x['slug']??'')!==$slug && (string)($x['slug']??'')!==''){ $oldPath=parse_url(locale_url($loc,'category/'.(string)$x['slug']),PHP_URL_PATH)?:'/'; $newPath=parse_url(locale_url($loc,'category/'.$slug),PHP_URL_PATH)?:'/'; try{Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,301) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),http_code=301',[$oldPath,$newPath]);}catch(\Throwable){} } if($x)Database::exec('UPDATE category_translations SET name=?,slug=?,description=?,seo_title=?,seo_description=?,status=? WHERE id=?',[$name,$slug,$desc,$seoTitle,$seoDescription,$translationStatus,$x['id']]); else Database::insert('INSERT INTO category_translations(category_id,locale,name,slug,description,seo_title,seo_description,status) VALUES(?,?,?,?,?,?,?,?)',[$id,$loc,$name,$slug,$desc,$seoTitle,$seoDescription,$translationStatus]);}
        flash('success','تم حفظ التصنيف.'); redirect('/admin/categories');
    }

    public function saveTag(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $source=trim((string)($_POST['name'][default_locale()]??'')); if($source===''){flash('error','اكتب اسم الوسم باللغة الأساسية.');redirect('/admin/categories#tags');}
        if(!$id)$id=Database::insert('INSERT INTO tags(name,slug) VALUES(?,?)',[$source,slugify((string)($_POST['slug'][default_locale()]??$source))]);
        else Database::exec('UPDATE tags SET name=?,slug=? WHERE id=?',[$source,slugify((string)($_POST['slug'][default_locale()]??$source)),$id]);
        foreach(enabled_locales() as $locale=>$language){$name=trim((string)($_POST['name'][$locale]??''));if($name==='')continue;$slug=slugify((string)($_POST['slug'][$locale]??$name));$description=trim((string)($_POST['description'][$locale]??''));$seoTitle=mb_substr(trim((string)($_POST['seo_title'][$locale]??'')),0,255);$seoDescription=mb_substr(trim((string)($_POST['seo_description'][$locale]??'')),0,320);$status=(string)($_POST['translation_status'][$locale]??'draft');if(!in_array($status,['draft','review','published'],true))$status='draft';Database::exec('INSERT INTO tag_translations(tag_id,locale,name,slug,description,seo_title,seo_description,status) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),description=VALUES(description),seo_title=VALUES(seo_title),seo_description=VALUES(seo_description),status=VALUES(status),updated_at=CURRENT_TIMESTAMP',[$id,$locale,$name,$slug,$description,$seoTitle,$seoDescription,$status]);}
        flash('success','تم حفظ الوسم وترجماته.');redirect('/admin/categories#tags');
    }

    public function ads(): void {
        $this->adminGuard(); $ads=Database::all('SELECT * FROM ads ORDER BY id DESC');
        View::render('admin/ads',compact('ads')+['title'=>'مركز الإعلانات'],'dashboard');
    }
    public function saveAd(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0);
        $allowedSlots=['below_header','home_top','home_after_featured','home_between_sections','article_top','article_sidebar','inside_article_3','inside_article_7','inside_article_end','footer_top'];
        $slot=(string)($_POST['slot']??''); if(!in_array($slot,$allowedSlots,true)) $slot='below_header';
        $device=in_array($_POST['device']??'all',['all','desktop','mobile'],true)?$_POST['device']:'all'; $starts=trim((string)($_POST['starts_at']??''))?:null; $ends=trim((string)($_POST['ends_at']??''))?:null;
        $data=[$slot,(string)($_POST['locale']??'*'),(string)($_POST['type']??'image'),trim((string)($_POST['label']??'')),trim((string)($_POST['advertiser_name']??'')),trim((string)($_POST['price_note']??'')),trim((string)($_POST['admin_note']??'')),trim((string)($_POST['image_url']??'')),trim((string)($_POST['target_url']??'')),(string)($_POST['code']??''),isset($_POST['active'])?1:0,$starts,$ends,$device];
        if($id)Database::exec('UPDATE ads SET slot=?,locale=?,type=?,label=?,advertiser_name=?,price_note=?,admin_note=?,image_url=?,target_url=?,code=?,active=?,starts_at=?,ends_at=?,device=? WHERE id=?',[...$data,$id]); else Database::insert('INSERT INTO ads(slot,locale,type,label,advertiser_name,price_note,admin_note,image_url,target_url,code,active,starts_at,ends_at,device) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$data);
        audit_log('ad_save','ad',$id?:null,$slot); flash('success','تم حفظ الإعلان وموضع ظهوره.'); redirect('/admin/ads');
    }
    public function toggleAd(): void { $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); Database::exec('UPDATE ads SET active=IF(active=1,0,1) WHERE id=?',[$id]); redirect('/admin/ads'); }
    public function deleteAd(): void { $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); Database::exec('DELETE FROM ads WHERE id=?',[$id]); flash('success','تم حذف الإعلان.'); redirect('/admin/ads'); }

    public function navigation(): void {
        $this->adminGuard();
        $catalog=array_filter(site_navigation_catalog(default_locale()),static fn($item,$key)=>!str_starts_with((string)$key,'footer_custom:'),ARRAY_FILTER_USE_BOTH);
        $headerMenu=json_decode(setting('header_menu_json',''),true); if(!is_array($headerMenu)) $headerMenu=['home','categories','podcast','khawatir','community','story'];
        $footerMenu=json_decode(setting('footer_menu_json',''),true); if(!is_array($footerMenu)) $footerMenu=['story','explore','community','behind'];
        $mobileMenu=json_decode(setting('mobile_menu_json',''),true); if(!is_array($mobileMenu)) $mobileMenu=$headerMenu;
        $iconOverrides=json_decode(setting('nav_icon_overrides_json',''),true); if(!is_array($iconOverrides)) $iconOverrides=[];
        $localeVisibility=json_decode(setting('nav_locale_visibility_json','{}'),true); if(!is_array($localeVisibility)) $localeVisibility=[];
        $customRows=json_decode(setting('custom_navigation_json',''),true); if(!is_array($customRows)) $customRows=[];
        $customById=[]; foreach($customRows as $row){ if(is_array($row)&&!empty($row['id'])) $customById[(string)$row['id']]=$row; }
        $slots=[]; for($i=1;$i<=16;$i++) $slots[$i]=$customById['slot'.$i]??['id'=>'slot'.$i,'label'=>'','url'=>'','icon'=>'link','new_tab'=>0,'display'=>'both','style_type'=>'link','bg'=>'#157176','text_color'=>'#ffffff','hover_bg'=>'#0f5f63','border_color'=>'#157176','radius'=>10,'size'=>'md'];
        View::render('admin/navigation',compact('catalog','headerMenu','footerMenu','iconOverrides','localeVisibility','slots')+['title'=>'القوائم والتنقل'],'dashboard');
    }

    public function saveNavigation(): void {
        $this->adminGuard(); Csrf::enforce();
        $icons=array_keys(navigation_icon_names());
        $catalog=site_navigation_catalog(default_locale());
        $iconOverrides=[]; $header=[]; $footer=[]; $mobile=[]; $localeVisibility=[];
        $postedLocales=is_array($_POST['nav_locales']??null)?$_POST['nav_locales']:[];
        $headerChecks=is_array($_POST['nav_header']??null)?$_POST['nav_header']:[];
        $footerChecks=is_array($_POST['nav_footer']??null)?$_POST['nav_footer']:[];
        $mobileChecks=is_array($_POST['nav_mobile']??null)?$_POST['nav_mobile']:[];
        $headerOrders=is_array($_POST['nav_header_order']??null)?$_POST['nav_header_order']:[];
        $footerOrders=is_array($_POST['nav_footer_order']??null)?$_POST['nav_footer_order']:[];
        $mobileOrders=is_array($_POST['nav_mobile_order']??null)?$_POST['nav_mobile_order']:[];
        $postedIcons=is_array($_POST['nav_icon']??null)?$_POST['nav_icon']:[];
        foreach($catalog as $key=>$item){
            if(str_starts_with((string)$key,'custom:') || str_starts_with((string)$key,'footer_custom:')) continue;
            $icon=in_array((string)($postedIcons[$key]??''),$icons,true)?(string)$postedIcons[$key]:(string)($item['icon']??'link');
            $iconOverrides[$key]=$icon;
            $allowedLocaleCodes=array_keys(enabled_locales());
            $selected=is_array($postedLocales[$key]??null)?array_values(array_intersect($allowedLocaleCodes,array_map('strval',$postedLocales[$key]))):$allowedLocaleCodes;
            if($key==='community') $selected=['ar'];
            $localeVisibility[$key]=$selected?:$allowedLocaleCodes;
            if(isset($headerChecks[$key])) $header[]=['key'=>$key,'order'=>(int)($headerOrders[$key]??50)];
            if(isset($footerChecks[$key])) $footer[]=['key'=>$key,'order'=>(int)($footerOrders[$key]??50)];
            if(isset($mobileChecks[$key])) $mobile[]=['key'=>$key,'order'=>(int)($mobileOrders[$key]??50)];
        }
        $custom=[]; $customPost=is_array($_POST['custom']??null)?$_POST['custom']:[];
        foreach(range(1,16) as $i){
            $id='slot'.$i; $row=is_array($customPost[$i]??null)?$customPost[$i]:[];
            $label=trim((string)($row['label']??'')); $href=trim((string)($row['url']??''));
            $allowedLocaleCodes=array_keys(enabled_locales());
            $labels=[];
            $postedLabels=is_array($row['labels']??null)?$row['labels']:[];
            foreach($allowedLocaleCodes as $lc){
                $v=mb_substr(trim((string)($postedLabels[$lc]??'')),0,120);
                if($v!=='') $labels[$lc]=$v;
            }
            if($label===''){ $label=(string)($labels[default_locale()]??''); if($label==='' && $labels){ $first=reset($labels); $label=is_string($first)?$first:''; } }
            if($label==='' || $href==='') continue;
            if(!preg_match('#^(?:https?://|/|#|mailto:|tel:)#i',$href)) $href='/'.ltrim($href,'/');
            $icon=in_array((string)($row['icon']??''),$icons,true)?(string)$row['icon']:'link';
            $selected=is_array($row['locales']??null)?array_values(array_intersect($allowedLocaleCodes,array_map('strval',$row['locales']))):$allowedLocaleCodes;
            $display=in_array((string)($row['display']??'both'),['both','desktop','mobile'],true)?(string)$row['display']:'both';
            $styleType=in_array((string)($row['style_type']??'link'),['link','button'],true)?(string)$row['style_type']:'link';
            $size=in_array((string)($row['size']??'md'),['sm','md','lg'],true)?(string)$row['size']:'md';
            $cleanColor=static fn($v,$fallback)=>preg_match('/^#[0-9a-fA-F]{6}$/',(string)$v)?strtolower((string)$v):$fallback;
            $custom[]=['id'=>$id,'label'=>mb_substr($label,0,120),'labels'=>$labels,'url'=>mb_substr($href,0,500),'icon'=>$icon,'new_tab'=>isset($row['new_tab'])?1:0,'locales'=>$selected?:$allowedLocaleCodes,'display'=>$display,'style_type'=>$styleType,'bg'=>$cleanColor($row['bg']??'','#157176'),'text_color'=>$cleanColor($row['text_color']??'','#ffffff'),'hover_bg'=>$cleanColor($row['hover_bg']??'','#0f5f63'),'border_color'=>$cleanColor($row['border_color']??'','#157176'),'radius'=>max(0,min(40,(int)($row['radius']??10))),'size'=>$size];
            $key='custom:'.$id; $iconOverrides[$key]=$icon; $localeVisibility[$key]=$selected?:$allowedLocaleCodes;
            if(isset($row['header'])) $header[]=['key'=>$key,'order'=>(int)($row['header_order']??80)];
            if(isset($row['footer'])) $footer[]=['key'=>$key,'order'=>(int)($row['footer_order']??80)];
            if(isset($row['mobile'])) $mobile[]=['key'=>$key,'order'=>(int)($row['mobile_order']??80)];
        }
        // Footer Studio owns its dedicated links. Saving the broader
        // navigation screen must not erase them.
        $existingFooter=json_decode(setting('footer_menu_json',''),true);
        if(is_array($existingFooter)) foreach($existingFooter as $index=>$key){
            if(is_string($key) && str_starts_with($key,'footer_custom:') && isset($catalog[$key])) $footer[]=['key'=>$key,'order'=>800+(int)$index];
        }
        usort($header,fn($a,$b)=>$a['order']<=>$b['order']); usort($footer,fn($a,$b)=>$a['order']<=>$b['order']); usort($mobile,fn($a,$b)=>$a['order']<=>$b['order']);
        $this->putSetting('custom_navigation_json',json_encode($custom,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $this->putSetting('nav_icon_overrides_json',json_encode($iconOverrides,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $this->putSetting('nav_locale_visibility_json',json_encode($localeVisibility,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $this->putSetting('header_menu_json',json_encode(array_column($header,'key'),JSON_UNESCAPED_UNICODE));
        $this->putSetting('footer_menu_json',json_encode(array_column($footer,'key'),JSON_UNESCAPED_UNICODE));
        $this->putSetting('mobile_menu_json',json_encode(array_column($mobile,'key'),JSON_UNESCAPED_UNICODE));
        audit_log('navigation_save','settings',null,'header footer navigation');
        flash('success','تم حفظ قوائم الهيدر والفوتر. الصفحات الجديدة تظهر تلقائيًا هنا للاختيار.');
        redirect('/admin/navigation');
    }

    public function appearance(): void {
        $this->adminGuard();
        $settings=Database::all('SELECT `key`,value FROM settings ORDER BY `key`');
        $map=[]; foreach($settings as $row) $map[$row['key']]=$row['value'];
        $catalog=array_filter(site_navigation_catalog(default_locale()),fn($v,$k)=>!str_starts_with((string)$k,'custom:'),ARRAY_FILTER_USE_BOTH);
        $headerMenu=json_decode($map['header_menu_json']??'',true); if(!is_array($headerMenu)) $headerMenu=['home','categories','podcast','khawatir','community','story'];
        $footerMenu=json_decode($map['footer_menu_json']??'',true); if(!is_array($footerMenu)) $footerMenu=['story','explore','community'];
        $iconOverrides=json_decode($map['nav_icon_overrides_json']??'',true); if(!is_array($iconOverrides)) $iconOverrides=[];
        $customRows=json_decode($map['custom_navigation_json']??'',true); if(!is_array($customRows)) $customRows=[];
        $customById=[]; foreach($customRows as $row){ if(is_array($row)&&!empty($row['id'])) $customById[(string)$row['id']]=$row; }
        $slots=[]; for($i=1;$i<=12;$i++) $slots[$i]=$customById['slot'.$i]??['id'=>'slot'.$i,'label'=>'','url'=>'','icon'=>'link','new_tab'=>0];
        View::render('admin/appearance',compact('map','catalog','headerMenu','footerMenu','iconOverrides','slots')+['title'=>'تصميم الهيدر'],'dashboard');
    }

    public function saveAppearance(): void {
        $this->adminGuard(); Csrf::enforce();
        $color=function(string $name,string $fallback): string {
            $v=trim((string)($_POST[$name]??''));
            return preg_match('/^#[0-9A-Fa-f]{6}$/',$v)?strtolower($v):$fallback;
        };
        $url=function(string $value): string {
            $value=trim($value);
            if($value==='' || !preg_match('#^(?:https?://|/|#|mailto:)#i',$value)) return '';
            return substr($value,0,500);
        };
        $this->putSetting('news_ticker_label',trim((string)($_POST['news_ticker_label']??'أهم المقالات')));
        $this->putSetting('header_background',$color('header_background','#ffffff'));
        $this->putSetting('header_scrolled_background',$color('header_scrolled_background','#ffffff'));
        $this->putSetting('header_text_color',$color('header_text_color','#152d31'));
        $this->putSetting('header_border_color',$color('header_border_color','#e1e8e9'));
        $this->putSetting('header_hover_color',$color('header_hover_color','#157176'));
        $this->putSetting('header_hover_background',$color('header_hover_background','#eaf6f6'));
        $this->putSetting('header_ticker_background',$color('header_ticker_background','#071118'));
        $this->putSetting('header_ticker_text',$color('header_ticker_text','#f4fbfb'));
        $this->putSetting('header_ticker_accent',$color('header_ticker_accent','#37c1c7'));
        $this->putSetting('header_mega_background',$color('header_mega_background','#121a1d'));
        $this->putSetting('header_mega_text',$color('header_mega_text','#f6fbfb'));
        $this->putSetting('header_mega_card',$color('header_mega_card','#1c2529'));
        $this->putSetting('header_mobile_background',$color('header_mobile_background','#ffffff'));
        $this->putSetting('header_mobile_text',$color('header_mobile_text','#163438'));
        $height=max(64,min(110,(int)($_POST['header_height']??86))); $this->putSetting('header_height',(string)$height);
        $tickerSpeed=max(12,min(120,(int)($_POST['news_ticker_speed']??32))); $this->putSetting('news_ticker_speed',(string)$tickerSpeed);
        foreach(['header_sticky','header_scroll_compact','header_hide_on_scroll','header_show_tagline','header_show_search','header_show_theme','header_show_icons','header_show_socials','header_show_categories_mega','header_shadow','news_ticker_enabled'] as $k){
            $this->putSetting($k,isset($_POST[$k])?'1':'0');
        }
        foreach(['facebook','instagram','x','youtube','telegram'] as $social){ $field='footer_social_'.$social; if(array_key_exists($field,$_POST)) $this->putSetting($field,$url((string)$_POST[$field])); }

        // Menu composition is saved only from /admin/navigation.
        audit_log('appearance_save','settings',null,'header footer navigation');
        flash('success','تم حفظ تصميم الهيدر وتطبيقه على الموقع.');
        redirect('/admin/appearance');
    }

    public function typography(): void {
        $this->adminGuard();
        $rows=Database::all("SELECT `key`,value FROM settings WHERE `key` LIKE 'typography_%'"); $map=[]; foreach($rows as $row)$map[$row['key']]=$row['value'];
        View::render('admin/typography',['map'=>$map,'catalog'=>typography_catalog(),'defaults'=>typography_defaults(),'title'=>'الخطوط والمقاسات'],'dashboard');
    }
    public function saveTypography(): void {
        $this->adminGuard(); Csrf::enforce();
        $families=['plex','system','serif','mono']; $weights=[300,400,500,600,700,800,900];
        foreach(typography_defaults() as $element=>$devices) foreach($devices as $device=>$defaults) foreach($defaults as $field=>$fallback){
            $name="typography_{$element}_{$device}_{$field}"; $raw=$_POST[$name]??$fallback;
            if($field==='family') $value=in_array((string)$raw,$families,true)?(string)$raw:$fallback;
            elseif($field==='size') $value=(string)max(10,min(120,(int)$raw));
            elseif($field==='weight') $value=(string)(in_array((int)$raw,$weights,true)?(int)$raw:$fallback);
            elseif($field==='line') $value=(string)max(.8,min(3,(float)$raw));
            else $value=(string)max(-5,min(30,(float)$raw));
            $this->putSetting($name,$value);
        }
        audit_log('typography_save','settings',null,'responsive typography'); flash('success','تم حفظ الخطوط والمقاسات لجميع الأجهزة.'); redirect('/admin/typography');
    }

    public function headerEditor(): void {
        $this->adminGuard();
        $rows=Database::all('SELECT `key`,value FROM settings ORDER BY `key`'); $map=[]; foreach($rows as $r)$map[$r['key']]=$r['value'];
        $catalog=array_filter(site_navigation_catalog(default_locale()),fn($v,$k)=>!str_starts_with((string)$k,'custom:'),ARRAY_FILTER_USE_BOTH);
        $headerMenu=json_decode($map['header_menu_json']??'',true); if(!is_array($headerMenu))$headerMenu=['home','categories','podcast','khawatir','community','story'];
        View::render('admin/header-editor',compact('map','catalog','headerMenu')+['title'=>'تعديل الهيدر'],'dashboard');
    }
    public function saveHeaderEditor(): void {
        $this->adminGuard(); Csrf::enforce();
        $bools=['header_sticky','header_scroll_compact','header_show_search','header_show_theme','header_show_icons','header_show_socials','header_show_categories_mega','header_shadow','header_account_cta_enabled','header_account_cta_mobile'];
        foreach($bools as $k)$this->putSetting($k,isset($_POST[$k])?'1':'0');
        $height=max(64,min(110,(int)($_POST['header_height']??86))); $this->putSetting('header_height',(string)$height);
        foreach(['header_background'=>'#ffffff','header_text_color'=>'#152d31','header_hover_color'=>'#157176'] as $k=>$d){$v=(string)($_POST[$k]??$d);$this->putSetting($k,preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):$d);}
        $selected=is_array($_POST['header_items']??null)?array_map('strval',$_POST['header_items']):[]; $catalog=site_navigation_catalog(default_locale()); $menu=[]; foreach($selected as $k)if(isset($catalog[$k])&&!str_starts_with($k,'custom:'))$menu[]=$k; $this->putSetting('header_menu_json',json_encode(array_values(array_unique($menu)),JSON_UNESCAPED_UNICODE));
        audit_log('header_save','settings',null,'simple header editor'); flash('success','تم حفظ الهيدر.'); redirect('/admin/header');
    }
    public function footerEditor(): void {
        $this->adminGuard(); $rows=Database::all('SELECT `key`,value FROM settings ORDER BY `key`'); $map=[]; foreach($rows as $r)$map[$r['key']]=$r['value'];
        $catalog=site_navigation_catalog(default_locale());
        $footerMenu=json_decode($map['footer_menu_json']??'',true); if(!is_array($footerMenu))$footerMenu=['story','explore','community'];
        View::render('admin/footer-editor',compact('map','catalog','footerMenu')+['title'=>'تعديل الفوتر'],'dashboard');
    }
    public function saveFooterEditor(): void {
        $this->adminGuard(); Csrf::enforce();
        $this->putSetting('footer_show_languages','0');
        foreach(['footer_enabled','footer_show_logo','footer_show_socials','footer_show_categories','footer_show_cta','footer_show_story_button','footer_show_writer_button','footer_show_behind_button','footer_show_email','footer_show_newsletter','footer_back_to_top','footer_designer_enabled','footer_designer_new_tab'] as $k) $this->putSetting($k,isset($_POST[$k])?'1':'0');

        foreach(['footer_title','footer_description','footer_menu_title','footer_categories_title','footer_cta_title','footer_cta_text','footer_cta_label','footer_story_label','footer_writer_label','footer_behind_label','footer_copyright','footer_newsletter_title','footer_newsletter_text','footer_contact_email','footer_designer_prefix_ar','footer_designer_name'] as $k){
            $value=trim((string)($_POST[$k]??''));
            $limit=in_array($k,['footer_description','footer_cta_text','footer_newsletter_text'],true)?900:220;
            $this->putSetting($k,mb_substr($value,0,$limit));
        }

        foreach(['footer_cta_url'=>'/contact','footer_story_url'=>'/send-story','footer_writer_url'=>'/writer-application','footer_behind_url'=>'/page/من-خلف-سراديب','footer_designer_url'=>''] as $k=>$fallback){
            $u=trim((string)($_POST[$k]??$fallback));
            if($u!=='' && !preg_match('#^(?:https?://|mailto:|tel:|/)#i',$u)) $u=$fallback;
            $this->putSetting($k,mb_substr($u,0,600));
        }

        foreach([
            'footer_background'=>'#071019','footer_text_color'=>'#c7d7db','footer_heading_color'=>'#ffffff','footer_accent_color'=>'#31bec5',
            'footer_button_background'=>'#31bec5','footer_button_text'=>'#071519','footer_border_color'=>'#244348'
        ] as $k=>$fallback){
            $v=trim((string)($_POST[$k]??$fallback));
            $this->putSetting($k,preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):$fallback);
        }

        $layout=(string)($_POST['footer_layout']??'balanced');
        if(!in_array($layout,['balanced','wide-brand','compact'],true)) $layout='balanced';
        $this->putSetting('footer_layout',$layout);
        foreach([
            'footer_padding_top'=>[36,120,72],'footer_padding_bottom'=>[24,100,56],'footer_column_gap'=>[16,90,48],
            'footer_logo_height'=>[34,120,74],'footer_social_size'=>[30,58,38],'footer_heading_size'=>[12,26,19],
            'footer_text_size'=>[11,19,14],'footer_element_radius'=>[0,28,11]
        ] as $k=>$cfg){
            $v=max($cfg[0],min($cfg[1],(int)($_POST[$k]??$cfg[2])));
            $this->putSetting($k,(string)$v);
        }

        // Dedicated footer links are intentionally stored apart from the
        // global navigation builder. This lets the footer be managed without
        // changing the header or mobile menu.
        $footerCustom=[]; $footerCustomKeys=[];
        $postedCustom=is_array($_POST['footer_custom']??null)?$_POST['footer_custom']:[];
        $cleanColor=static fn($value,string $fallback): string => preg_match('/^#[0-9a-fA-F]{6}$/',(string)$value)?strtolower((string)$value):$fallback;
        foreach(range(1,6) as $i){
            $row=is_array($postedCustom[$i]??null)?$postedCustom[$i]:[];
            $label=mb_substr(trim((string)($row['label']??'')),0,120);
            $href=mb_substr(trim((string)($row['url']??'')),0,500);
            if($label==='' || $href==='') continue;
            if(!preg_match('#^(?:https?://|/|#|mailto:|tel:)#i',$href)) $href='/'.ltrim($href,'/');
            $id='footer'.$i;
            $show=isset($row['show'])?1:0;
            $order=max(0,min(999,(int)($row['order']??80+$i)));
            $footerCustom[]=['id'=>$id,'label'=>$label,'url'=>$href,'icon'=>'link','new_tab'=>isset($row['new_tab'])?1:0,'style_type'=>in_array((string)($row['style_type']??'link'),['link','button'],true)?(string)$row['style_type']:'link','bg'=>$cleanColor($row['bg']??'','#157176'),'text_color'=>$cleanColor($row['text_color']??'','#ffffff'),'hover_bg'=>$cleanColor($row['hover_bg']??'','#0f5f63'),'border_color'=>$cleanColor($row['border_color']??'','#157176'),'radius'=>max(0,min(40,(int)($row['radius']??10))),'size'=>in_array((string)($row['size']??'md'),['sm','md','lg'],true)?(string)$row['size']:'md','show'=>$show,'order'=>$order];
            if($show) $footerCustomKeys['footer_custom:'.$id]=$order;
        }
        $this->putSetting('footer_custom_links_json',json_encode($footerCustom,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $selected=is_array($_POST['footer_items']??null)?array_map('strval',$_POST['footer_items']):[];
        $orders=is_array($_POST['footer_item_order']??null)?$_POST['footer_item_order']:[];
        $catalog=site_navigation_catalog(default_locale()); $menu=[];
        foreach($selected as $key) if(isset($catalog[$key])) $menu[$key]=max(0,min(999,(int)($orders[$key]??50)));
        foreach($footerCustomKeys as $key=>$order) $menu[$key]=$order;
        asort($menu,SORT_NUMERIC);
        $this->putSetting('footer_menu_json',json_encode(array_values(array_keys($menu)),JSON_UNESCAPED_UNICODE));

        audit_log('footer_save','settings',null,'footer studio v2');
        flash('success','تم حفظ تصميم الفوتر وتطبيقه على الموقع.');
        redirect('/admin/footer');
    }

    public function whatsapp(): void {
        $this->adminGuard();
        $rows=Database::all('SELECT `key`,value FROM settings ORDER BY `key`'); $map=[]; foreach($rows as $r)$map[$r['key']]=$r['value'];
        View::render('admin/whatsapp',compact('map')+['title'=>'واتساب'],'dashboard');
    }
    public function saveWhatsapp(): void {
        $this->adminGuard(); Csrf::enforce();
        $bools=['wa_enabled','wa_show_logo','wa_show_mobile','wa_show_desktop','wa_float_animation','wa_ripple_animation','wa_open_new_tab','wa_include_context'];
        foreach($bools as $k) $this->putSetting($k,isset($_POST[$k])?'1':'0');
        $this->putSetting('wa_brand_name',trim((string)($_POST['wa_brand_name']??'')));
        $this->putSetting('wa_position','right');
        foreach(['wa_logo_width'=>[80,420],'wa_button_desktop'=>[44,110],'wa_button_mobile'=>[44,90],'wa_bottom_desktop'=>[0,400],'wa_side_desktop'=>[0,400],'wa_bottom_mobile'=>[0,200],'wa_side_mobile'=>[0,200],'wa_modal_width'=>[320,760],'wa_modal_radius'=>[0,40]] as $k=>$lim){ $v=max($lim[0],min($lim[1],(int)($_POST[$k]??0))); $this->putSetting($k,(string)$v); }
        foreach(['wa_primary_color'=>'#157176','wa_secondary_color'=>'#2a969b','wa_icon_color'=>'#ffffff','wa_ripple_color'=>'#157176','wa_modal_bg'=>'#ffffff','wa_modal_text'=>'#173136','wa_muted_text'=>'#61767a','wa_logo_bg'=>'#ffffff'] as $k=>$fallback){ $v=trim((string)($_POST[$k]??'')); $this->putSetting($k,preg_match('/^#[0-9a-fA-F]{6}$/',$v)?strtolower($v):$fallback); }
        foreach(range(1,4) as $i){
            $this->putSetting('wa_contact_'.$i.'_enabled',isset($_POST['wa_contact_'.$i.'_enabled'])?'1':'0');
            $this->putSetting('wa_contact_'.$i.'_phone',preg_replace('/[^0-9]/','',(string)($_POST['wa_contact_'.$i.'_phone']??'')));
            $this->putSetting('wa_contact_'.$i.'_name',trim((string)($_POST['wa_contact_'.$i.'_name']??'')));
            foreach(array_keys(whatsapp_default_texts()) as $lang) $this->putSetting('wa_contact_'.$i.'_role_'.$lang,trim((string)($_POST['wa_contact_'.$i.'_role_'.$lang]??'')));
        }
        foreach(whatsapp_default_texts() as $lang=>$rows) foreach($rows as $key=>$fallback) $this->putSetting('wa_text_'.$lang.'_'.$key,trim((string)($_POST['wa_text_'.$lang.'_'.$key]??$fallback)));
        try{ $logo=Upload::image($_FILES['wa_logo']??[]); if($logo) $this->putSetting('wa_logo',$logo); }catch(\Throwable $e){ flash('error',$e->getMessage()); redirect('/admin/whatsapp'); }
        if(isset($_POST['wa_remove_logo'])) $this->putSetting('wa_logo','');
        audit_log('whatsapp_save','settings',null,'whatsapp'); flash('success','تم حفظ إعدادات واتساب.'); redirect('/admin/whatsapp');
    }
    public function social(): void {
        $this->adminGuard(); $rows=Database::all('SELECT `key`,value FROM settings ORDER BY `key`'); $map=[]; foreach($rows as $r)$map[$r['key']]=$r['value'];
        View::render('admin/social',compact('map')+['title'=>'السوشيال ميديا والمشاركة'],'dashboard');
    }
    public function saveSocial(): void {
        $this->adminGuard(); Csrf::enforce();
        $this->putSetting('article_share_enabled',isset($_POST['article_share_enabled'])?'1':'0');
        $this->putSetting('article_native_share',isset($_POST['article_native_share'])?'1':'0');
        $networks=['facebook','instagram','x','tiktok','youtube','telegram','linkedin','whatsapp'];
        foreach($networks as $key){
            $url=trim((string)($_POST['social_'.$key.'_url']??'')); if($url!==''&&!preg_match('#^(?:https?://|mailto:)#i',$url)) $url='';
            $this->putSetting('social_'.$key.'_url',$url);
            $this->putSetting('social_'.$key.'_enabled',isset($_POST['social_'.$key.'_enabled'])?'1':'0');
            $this->putSetting('social_'.$key.'_header',isset($_POST['social_'.$key.'_header'])?'1':'0');
            $this->putSetting('social_'.$key.'_footer',isset($_POST['social_'.$key.'_footer'])?'1':'0');
        }
        audit_log('social_save','settings',null,'social'); flash('success','تم حفظ الشبكات الاجتماعية وإعدادات المشاركة.'); redirect('/admin/social');
    }
    public function protection(): void { $this->adminGuard(); redirect('/admin/security'); }
    public function saveProtection(): void {
        $this->adminGuard(); Csrf::enforce();
        foreach(['protect_copy','protect_context','protect_select','protect_drag','protect_save_shortcut','protect_print','protect_image_overlay'] as $k) $this->putSetting($k,isset($_POST[$k])?'1':'0');
        $this->putSetting('protect_watermark_text',mb_substr(trim((string)($_POST['protect_watermark_text']??'')),0,80));
        audit_log('protection_save','settings',null,'protection'); flash('success','تم حفظ إعدادات حماية المحتوى.'); redirect('/admin/security');
    }

    public function settings(): void {
        $this->adminGuard(); $settings=Database::all('SELECT `key`,value FROM settings ORDER BY `key`'); $map=[]; foreach($settings as $s)$map[$s['key']]=$s['value'];
        $categories=Database::all("SELECT c.id,COALESCE(ct.name,CONCAT('تصنيف ',c.id)) name FROM categories c LEFT JOIN category_translations ct ON ct.category_id=c.id AND ct.locale=? ORDER BY c.sort_order,c.id",[default_locale()]);
        View::render('admin/settings',compact('map','categories')+['title'=>'إعدادات الموقع'],'dashboard');
    }
    public function saveSettings(): void {
        $this->adminGuard(); Csrf::enforce();
        $keys=['site_name','tagline','contact_email','writer_application_email','contact_form_email','story_submission_email','footer_text','brand_color','brand_mark_text','brand_mark_bg','brand_mark_color','brand_logo_height','news_ticker_label','news_ticker_speed','news_ticker_source','news_ticker_category','adblock_mode','smtp_host','smtp_port','smtp_username','smtp_encryption','smtp_from_email','smtp_from_name'];
        foreach($keys as $k) $this->putSetting($k,(string)($_POST[$k]??''));
        $this->putSetting('brand_mark_enabled',isset($_POST['brand_mark_enabled'])?'1':'0');
        $this->putSetting('news_ticker_enabled',isset($_POST['news_ticker_enabled'])?'1':'0');
        $this->putSetting('mail_notifications',isset($_POST['mail_notifications'])?'1':'0');
        $this->putSetting('smtp_enabled',isset($_POST['smtp_enabled'])?'1':'0');
        if(trim((string)($_POST['smtp_password']??''))!=='') $this->putSetting('smtp_password',(string)$_POST['smtp_password']);
        $this->putSetting('adblock_notice_enabled',isset($_POST['adblock_notice_enabled'])?'1':'0');
        try {
            $logo=Upload::image($_FILES['brand_logo']??[]);
            if($logo) $this->putSetting('brand_logo',$logo);
        } catch(\Throwable $e){ flash('error',$e->getMessage()); redirect('/admin/settings'); }
        if(isset($_POST['remove_brand_logo'])) $this->putSetting('brand_logo','');
        audit_log('settings_save','settings',null,'site settings'); flash('success','تم حفظ إعدادات الموقع والهوية واللغات.'); redirect('/admin/settings');
    }
    private function putSetting(string $key,string $value): void {
        $x=Database::one('SELECT `key` FROM settings WHERE `key`=?',[$key]);
        if($x) Database::exec('UPDATE settings SET value=? WHERE `key`=?',[$value,$key]); else Database::insert('INSERT INTO settings(`key`,value) VALUES(?,?)',[$key,$value]);
    }


    public function testMail(): void {
        $this->adminGuard(); Csrf::enforce();
        $to=trim((string)($_POST['test_email']??setting('contact_email','')));
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)){ flash('error','أدخل بريد اختبار صحيحًا.'); redirect('/admin/settings'); }
        $ok=send_site_mail($to,'اختبار بريد سراديب',"هذه رسالة اختبار من إعدادات البريد في سراديب.\n\nالوقت: ".date('Y-m-d H:i:s'));
        audit_log('mail_test','settings',null,$ok?'sent':'failed');
        flash($ok?'success':'error',$ok?'تم إرسال رسالة الاختبار.':'تعذر إرسال رسالة الاختبار. راجع إعدادات SMTP أو سجل الخادم.');
        redirect('/admin/settings');
    }

    public function notes(): void {
        $u=$this->contentGuard();
        $fullAdmin=($u['role']??'')==='admin';
        $where=$fullAdmin?'1=1':"n.visibility='management'";
        $notes=Database::all("SELECT n.*,u.name creator_name,a.name assigned_name FROM admin_notes n JOIN users u ON u.id=n.user_id LEFT JOIN users a ON a.id=n.assigned_to WHERE {$where} ORDER BY n.pinned DESC, FIELD(n.status,'new','doing','done'), FIELD(n.priority,'urgent','important','normal'), n.due_at IS NULL, n.due_at, n.updated_at DESC LIMIT 250");
        $assignees=Database::all("SELECT id,name,role FROM users WHERE role IN ('admin','editor') AND status='active' ORDER BY name");
        View::render('admin/notes',compact('notes','assignees','fullAdmin')+['title'=>'الملاحظات والمهام'],'dashboard');
    }
    public function saveNote(): void {
        $u=$this->contentGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $title=trim((string)($_POST['title']??''));
        if($title===''){flash('error','اكتب عنوان الملاحظة.');redirect('/admin/notes');}
        $priority=in_array($_POST['priority']??'normal',['normal','important','urgent'],true)?$_POST['priority']:'normal';
        $status=in_array($_POST['status']??'new',['new','doing','done'],true)?$_POST['status']:'new';
        $visibility=(($u['role']??'')==='admin' && ($_POST['visibility']??'admin')==='admin')?'admin':'management';
        $assigned=(int)($_POST['assigned_to']??0)?:null; $due=trim((string)($_POST['due_at']??''))?:null; $body=trim((string)($_POST['body']??'')); $pinned=isset($_POST['pinned'])?1:0;
        if($id){
            $existing=Database::one('SELECT * FROM admin_notes WHERE id=?',[$id]); if(!$existing){flash('error','الملاحظة غير موجودة.');redirect('/admin/notes');}
            if(($u['role']??'')!=='admin' && ($existing['visibility']??'')==='admin'){http_response_code(403);exit('غير مصرح.');}
            Database::exec('UPDATE admin_notes SET assigned_to=?,title=?,body=?,priority=?,status=?,visibility=?,due_at=?,pinned=? WHERE id=?',[$assigned,$title,$body,$priority,$status,$visibility,$due,$pinned,$id]);
        }else{
            $id=Database::insert('INSERT INTO admin_notes(user_id,assigned_to,title,body,priority,status,visibility,due_at,pinned) VALUES(?,?,?,?,?,?,?,?,?)',[$u['id'],$assigned,$title,$body,$priority,$status,$visibility,$due,$pinned]);
        }
        audit_log('note_save','note',$id,$status); flash('success','تم حفظ الملاحظة.'); redirect('/admin/notes');
    }
    public function deleteNote(): void {
        $u=$this->contentGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $n=Database::one('SELECT visibility FROM admin_notes WHERE id=?',[$id]);
        if($n && (($u['role']??'')==='admin' || ($n['visibility']??'')==='management')){Database::exec('DELETE FROM admin_notes WHERE id=?',[$id]); audit_log('note_delete','note',$id,'');}
        redirect('/admin/notes');
    }
    public function activity(): void {
        $this->adminGuard();
        $logs=Database::all("SELECT l.*,u.name user_name,u.email FROM audit_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 500");
        View::render('admin/activity',compact('logs')+['title'=>'سجل النشاط'],'dashboard');
    }
    public function thanks(): void {
        $this->adminGuard();
        $writers=Database::all("SELECT u.id,u.name,u.username,u.avatar,u.writer_verified,u.writer_honored,p.professional_title FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id WHERE u.role IN ('writer','trusted_writer','editor','admin') AND u.status='active' ORDER BY u.name");
        $thanks=Database::all("SELECT t.*,u.name writer_name,u.username,u.avatar,u.writer_verified,u.writer_honored,p.professional_title FROM writer_thanks t JOIN users u ON u.id=t.user_id LEFT JOIN writer_profiles p ON p.user_id=u.id ORDER BY t.updated_at DESC");
        View::render('admin/thanks',compact('writers','thanks')+['title'=>'تكريم كتّاب سراديب'],'dashboard');
    }
    public function saveThanks(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $userId=(int)($_POST['user_id']??0);
        $title=mb_substr(trim((string)($_POST['title']??'')),0,190); $message=mb_substr(trim((string)($_POST['message']??'')),0,4000);
        $locale=(string)($_POST['locale']??'ar'); if(!preg_match('/^[a-z]{2,10}(?:-[A-Za-z0-9]+)?$/',$locale))$locale='ar';
        $awardType=in_array((string)($_POST['award_type']??'featured'),['featured','editor_choice','best_story','contributor'],true)?(string)$_POST['award_type']:'featured';
        $active=isset($_POST['active'])?1:0; $announce=isset($_POST['announce_on_site'])?1:0;
        $displayMode=in_array($_POST['display_mode']??'', ['session','visitor','interval','custom'],true)?$_POST['display_mode']:'session';
        $displayInterval=max(30,min(86400,(int)($_POST['display_interval']??60))); $displayDuration=max(4,min(30,(int)($_POST['display_duration']??7)));
        $starts=trim((string)($_POST['starts_at']??''))?:null; $ends=trim((string)($_POST['ends_at']??''))?:null;
        if(!$userId){flash('error','اختر الكاتب.');redirect('/admin/thanks');}
        $writer=Database::one("SELECT u.id,u.name,u.username,u.avatar,u.writer_verified,u.writer_honored,p.professional_title FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id WHERE u.id=? AND u.role IN ('writer','trusted_writer','editor','admin')",[$userId]); if(!$writer){flash('error','الكاتب غير موجود.');redirect('/admin/thanks');}
        $snapshot=json_encode(['writer_name'=>$writer['name'],'username'=>$writer['username'],'avatar'=>$writer['avatar'],'professional_title'=>$writer['professional_title'],'writer_verified'=>$writer['writer_verified'],'writer_honored'=>1,'title'=>$title,'message'=>$message,'locale'=>$locale,'award_type'=>$awardType],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $current=$id?Database::one('SELECT * FROM writer_thanks WHERE id=?',[$id]):null; $previousUserId=(int)($current['user_id']??0); $notificationId=(int)($current['notification_id']??0)?:null;
        if($id)Database::exec('UPDATE writer_thanks SET user_id=?,title=?,message=?,locale=?,award_type=?,announce_on_site=?,display_mode=?,display_interval=?,display_duration=?,starts_at=?,ends_at=?,snapshot_json=?,active=? WHERE id=?',[$userId,$title,$message,$locale,$awardType,$announce,$displayMode,$displayInterval,$displayDuration,$starts,$ends,$snapshot,$active,$id]);
        else $id=Database::insert('INSERT INTO writer_thanks(user_id,title,message,locale,award_type,announce_on_site,display_mode,display_interval,display_duration,starts_at,ends_at,snapshot_json,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$userId,$title,$message,$locale,$awardType,$announce,$displayMode,$displayInterval,$displayDuration,$starts,$ends,$snapshot,$active]);
        if($active) Database::exec('UPDATE users SET writer_honored=1 WHERE id=?',[$userId]);
        if($notificationId) Database::exec('UPDATE site_notifications SET active=0 WHERE id=?',[$notificationId]);
        $still=(int)(Database::one('SELECT COUNT(*) c FROM writer_thanks WHERE user_id=? AND active=1',[$userId])['c']??0); Database::exec('UPDATE users SET writer_honored=? WHERE id=?',[$still>0?1:0,$userId]); if($previousUserId>0 && $previousUserId!==$userId){$oldStill=(int)(Database::one('SELECT COUNT(*) c FROM writer_thanks WHERE user_id=? AND active=1',[$previousUserId])['c']??0);Database::exec('UPDATE users SET writer_honored=? WHERE id=?',[$oldStill>0?1:0,$previousUserId]);}
        audit_log('writer_thank_save','writer_thank',$id,(string)$userId); flash('success','تم حفظ تكريم الكاتب وتحديث ظهوره بالموقع.'); redirect('/admin/thanks');
    }
    public function siteNotifications(): void {
        $this->adminGuard();
        $items=Database::all('SELECT * FROM site_notifications ORDER BY id DESC LIMIT 100');
        View::render('admin/site-notifications',compact('items')+['title'=>'إشعارات ونوافذ الموقع'],'dashboard');
    }
    public function saveSiteNotification(): void {
        $this->adminGuard(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $title=trim((string)($_POST['title']??'')); $message=trim((string)($_POST['message']??''));
        if($title===''||$message===''){flash('error','العنوان والرسالة مطلوبان.');redirect('/admin/site-notifications');}
        $locale=(string)($_POST['locale']??'*'); $label=trim((string)($_POST['button_label']??'')); $url=trim((string)($_POST['button_url']??'')); $starts=trim((string)($_POST['starts_at']??''))?:null; $ends=trim((string)($_POST['ends_at']??''))?:null; $active=isset($_POST['active'])?1:0;
        if($id)Database::exec('UPDATE site_notifications SET title=?,message=?,locale=?,button_label=?,button_url=?,starts_at=?,ends_at=?,active=? WHERE id=?',[$title,$message,$locale,$label,$url,$starts,$ends,$active,$id]);
        else $id=Database::insert('INSERT INTO site_notifications(title,message,locale,button_label,button_url,starts_at,ends_at,active) VALUES(?,?,?,?,?,?,?,?)',[$title,$message,$locale,$label,$url,$starts,$ends,$active]);
        audit_log('site_notification_save','site_notification',$id,$locale); flash('success','تم حفظ إشعار الموقع.'); redirect('/admin/site-notifications');
    }
    public function deleteSiteNotification(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); Database::exec('DELETE FROM site_notifications WHERE id=?',[$id]); audit_log('site_notification_delete','site_notification',$id,''); redirect('/admin/site-notifications');
    }
    public function backup(): void {
        $this->adminGuard();
        View::render('admin/backup',['title'=>'النسخ الاحتياطي'],'dashboard');
    }
    public function downloadBackup(): void {
        $this->adminGuard(); Csrf::enforce();
        $sql=DatabaseBackup::sql();
        audit_log('backup_download','database',null,'sql');
        header('Content-Type: application/sql; charset=utf-8'); header('Content-Disposition: attachment; filename="saradeeeb-backup-'.date('Ymd-His').'.sql"'); header('Content-Length: '.strlen($sql)); echo $sql; exit;
    }


    public function integrations(): void {
        $this->adminGuard();
        $registry=Integrations::registry();
        $records=Integrations::records();
        $customCodes=Database::all('SELECT * FROM custom_tracking_codes ORDER BY id DESC');
        $slotKeys=['below_header','home_top','home_after_featured','home_between_sections','article_top','article_sidebar','inside_article_3','inside_article_7','inside_article_end','footer_top'];
        $slotMap=[]; foreach($slotKeys as $slot)$slotMap[$slot]=setting('ad_slot_enabled_'.$slot,'1')!=='0';
        $consent=[
            'enabled'=>setting('tracking_consent_enabled','0'),
            'version'=>setting('tracking_consent_version','1'),
            'title'=>setting('tracking_consent_title','خصوصيتك تهمنا'),
            'message'=>setting('tracking_consent_message','نستخدم أدوات قياس وإعلانات غير ضرورية فقط بعد موافقتك. يمكنك تغيير اختيارك في أي وقت.'),
            'policy_url'=>setting('tracking_privacy_url','/page/privacy-policy'),
        ];
        View::render('admin/integrations',compact('registry','records','customCodes','slotMap','consent')+['title'=>'التكاملات والتتبع'],'dashboard');
    }

    public function saveIntegration(): void {
        $u=$this->adminGuard(); Csrf::enforce();
        $service=(string)($_POST['service']??'');
        try {
            Integrations::save($service,$_POST,(int)$u['id']);
            audit_log('integration_save','integration',null,$service);
            flash('success','تم حفظ إعدادات التكامل بشكل مستقل.');
        } catch(\InvalidArgumentException $e) {
            flash('error',$e->getMessage());
        } catch(\Throwable $e) {
            flash('error','تعذر حفظ التكامل. تحقق من قاعدة البيانات ثم حاول مجددًا.');
        }
        redirect('/admin/integrations');
    }

    public function saveTrackingConsent(): void {
        $this->adminGuard(); Csrf::enforce();
        $version=preg_replace('/[^A-Za-z0-9._-]/','',(string)($_POST['version']??'1')) ?: '1';
        $title=mb_substr(trim((string)($_POST['title']??'')),0,120);
        $message=mb_substr(trim((string)($_POST['message']??'')),0,600);
        $policy=trim((string)($_POST['policy_url']??''));
        if($policy!=='' && !str_starts_with($policy,'/') && !filter_var($policy,FILTER_VALIDATE_URL)) $policy='/page/privacy-policy';
        $this->putSetting('tracking_consent_enabled',isset($_POST['enabled'])?'1':'0');
        $this->putSetting('tracking_consent_version',$version);
        $this->putSetting('tracking_consent_title',$title?:'خصوصيتك تهمنا');
        $this->putSetting('tracking_consent_message',$message?:'نستخدم أدوات قياس وإعلانات غير ضرورية فقط بعد موافقتك. يمكنك تغيير اختيارك في أي وقت.');
        $this->putSetting('tracking_privacy_url',$policy?:'/page/privacy-policy');
        audit_log('tracking_consent_save','settings',null,$version);
        flash('success','تم حفظ إعدادات موافقة التتبع.');
        redirect('/admin/integrations');
    }

    public function saveAdSlots(): void {
        $this->adminGuard(); Csrf::enforce();
        $slots=['below_header','home_top','home_after_featured','home_between_sections','article_top','article_sidebar','inside_article_3','inside_article_7','inside_article_end','footer_top'];
        foreach($slots as $slot)$this->putSetting('ad_slot_enabled_'.$slot,isset($_POST['slots'][$slot])?'1':'0');
        audit_log('ad_slots_save','settings',null,'ad placements');
        flash('success','تم حفظ تشغيل وإيقاف مواضع الإعلانات.');
        redirect('/admin/integrations');
    }

    public function saveCustomTrackingCode(): void {
        $u=$this->adminGuard(); Csrf::enforce();
        if(!isset($_POST['confirm_risk'])){ flash('error','يجب تأكيد فهم مخاطر الكود المخصص قبل حفظه.'); redirect('/admin/integrations'); }
        $name=mb_substr(trim((string)($_POST['name']??'')),0,160);
        $code=trim((string)($_POST['code']??''));
        $location=in_array($_POST['location']??'', ['head','body_start','body_end'], true)?(string)$_POST['location']:'body_end';
        $category=in_array($_POST['consent_category']??'', ['essential','analytics','marketing','ads'], true)?(string)$_POST['consent_category']:'marketing';
        if($name==='' || $code===''){ flash('error','اسم الكود ومحتواه مطلوبان.'); redirect('/admin/integrations'); }
        if(strlen($code)>50000){ flash('error','الكود المخصص أكبر من الحد المسموح (50KB).'); redirect('/admin/integrations'); }
        $blocked=['<?php','<?=','eval(','document.write(','javascript:','srcdoc='];
        foreach($blocked as $needle){ if(stripos($code,$needle)!==false){ flash('error','تم رفض الكود لأنه يحتوي نمطًا عالي الخطورة: '.$needle); redirect('/admin/integrations'); } }
        $id=Database::insert('INSERT INTO custom_tracking_codes(name,location,consent_category,code,active,updated_by) VALUES(?,?,?,?,?,?)',[$name,$location,$category,$code,isset($_POST['active'])?1:0,(int)$u['id']]);
        audit_log('custom_tracking_code_create','custom_tracking_code',$id,$location.'|'.$category);
        flash('success','تم حفظ الكود المخصص.');
        redirect('/admin/integrations');
    }

    public function toggleCustomTrackingCode(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        Database::exec('UPDATE custom_tracking_codes SET active=IF(active=1,0,1),updated_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);
        audit_log('custom_tracking_code_toggle','custom_tracking_code',$id,'');
        redirect('/admin/integrations');
    }

    public function deleteCustomTrackingCode(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        Database::exec('DELETE FROM custom_tracking_codes WHERE id=?',[$id]);
        audit_log('custom_tracking_code_delete','custom_tracking_code',$id,'');
        flash('success','تم حذف الكود المخصص.');
        redirect('/admin/integrations');
    }

    public function exportArticlesForm(): void {
        $this->adminGuard();
        $default=default_locale();
        $authors=Database::all("SELECT id,name,username FROM users WHERE role IN ('writer','trusted_writer','editor','admin') ORDER BY name");
        $categories=Database::all("SELECT c.id,COALESCE((SELECT name FROM category_translations ct WHERE ct.category_id=c.id AND ct.locale=? LIMIT 1),(SELECT name FROM category_translations ct2 WHERE ct2.category_id=c.id ORDER BY ct2.id LIMIT 1)) name FROM categories c ORDER BY c.sort_order,c.id",[$default]);
        $locales=all_locales();
        $preset=[
            'status'=>(string)($_GET['status']??''),
            'author'=>(int)($_GET['author']??0),
            'category'=>(int)($_GET['category']??0),
            'locale'=>(string)($_GET['locale']??'*'),
        ];
        View::render('admin/export-articles',compact('authors','categories','locales','preset')+['title'=>'تصدير المقالات'],'dashboard');
    }

    public function exportArticles(): void {
        $u=$this->adminGuard(); Csrf::enforce();
        $format=in_array($_POST['format']??'csv',['csv','json','xml'],true)?(string)$_POST['format']:'csv';
        $status=(string)($_POST['status']??'');
        $author=(int)($_POST['author']??0); $category=(int)($_POST['category']??0);
        $locale=(string)($_POST['locale']??'*');
        if($locale!=='*' && !array_key_exists($locale,all_locales())) $locale='*';
        $where=[]; $params=[default_locale()];
        if(in_array($status,['draft','review','changes','published','archived'],true)){ $where[]='a.status=?'; $params[]=$status; }
        if($author>0){ $where[]='a.author_id=?'; $params[]=$author; }
        if($category>0){ $where[]='a.category_id=?'; $params[]=$category; }
        if($locale!=='*'){ $where[]='t.locale=?'; $params[]=$locale; }
        $sql="SELECT a.id,a.status,a.is_featured,a.featured_image,a.legacy_path,a.views,a.published_at,a.created_at,a.updated_at,u.name author_name,u.username author_username,COALESCE((SELECT name FROM category_translations cx WHERE cx.category_id=a.category_id AND cx.locale=? LIMIT 1),'') category_name,t.locale,t.title,t.slug,t.excerpt,t.content,t.seo_title,t.seo_description,t.seo_keywords,t.hashtags,t.og_image,t.status translation_status FROM articles a JOIN users u ON u.id=a.author_id JOIN article_translations t ON t.article_id=a.id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY a.id,t.locale";
        $rows=Database::all($sql,$params);
        $tagMap=[];
        if($rows){
            $ids=array_values(array_unique(array_map(static fn($r)=>(int)$r['id'],$rows)));
            $ph=implode(',',array_fill(0,count($ids),'?'));
            foreach(Database::all("SELECT at.article_id,GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR ', ') tags FROM article_tags at JOIN tags tg ON tg.id=at.tag_id WHERE at.article_id IN ({$ph}) GROUP BY at.article_id",$ids) as $tr) $tagMap[(int)$tr['article_id']]=(string)($tr['tags']??'');
        }
        foreach($rows as &$r) $r['tags']=$tagMap[(int)$r['id']]??'';
        unset($r);
        $stamp=date('Ymd-His');
        $base='saradeeeb-articles-'.$stamp;
        audit_log('articles_export','articles',null,$format.'|'.count($rows).'|'.$status.'|'.$locale);
        if($format==='json'){
            $payload=['source'=>'Saradeeeb','exported_at'=>date(DATE_ATOM),'count'=>count($rows),'articles'=>$rows];
            $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="'.$base.'.json"');
            header('Content-Length: '.strlen((string)$body)); echo $body; exit;
        }
        if($format==='xml'){
            $x=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_XML1,'UTF-8');
            $cdata=static fn($v)=>'<![CDATA['.str_replace(']]>',']]]]><![CDATA[>',(string)$v).']]>';
            $body="<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<saradeeeb-export version=\"1\" exported-at=\"".$x(date(DATE_ATOM))."\">\n";
            foreach($rows as $r){
                $body.="  <article id=\"".(int)$r['id']."\" locale=\"".$x($r['locale'])."\">\n";
                foreach($r as $k=>$v){ if(in_array($k,['id','locale'],true)) continue; $body.="    <".$k.">".(in_array($k,['content','excerpt'],true)?$cdata($v):$x($v))."</".$k.">\n"; }
                $body.="  </article>\n";
            }
            $body.="</saradeeeb-export>\n";
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="'.$base.'.xml"');
            header('Content-Length: '.strlen($body)); echo $body; exit;
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$base.'.csv"');
        echo "\xEF\xBB\xBF";
        $out=fopen('php://output','wb');
        $headers=['ID','الحالة','مميز','الصورة البارزة','الرابط القديم','المشاهدات','تاريخ النشر','تاريخ الإنشاء','آخر تحديث','الكاتب','اسم مستخدم الكاتب','التصنيف','اللغة','العنوان','Slug','المقتطف','المحتوى','SEO Title','SEO Description','SEO Keywords','Hashtags','OG Image','حالة الترجمة','الوسوم'];
        fputcsv($out,$headers);
        $safeCsv=static function($v){ $v=(string)$v; if($v!=='' && preg_match('/^[=+\-@]/u',$v)) $v="'".$v; return $v; };
        foreach($rows as $r){
            $vals=[$r['id'],$r['status'],$r['is_featured'],$r['featured_image'],$r['legacy_path'],$r['views'],$r['published_at'],$r['created_at'],$r['updated_at'],$r['author_name'],$r['author_username'],$r['category_name'],$r['locale'],$r['title'],$r['slug'],$r['excerpt'],$r['content'],$r['seo_title'],$r['seo_description'],$r['seo_keywords'],$r['hashtags'],$r['og_image'],$r['translation_status'],$r['tags']];
            fputcsv($out,array_map($safeCsv,$vals));
        }
        fclose($out); exit;
    }


    public function writerDetail(string $id): void {
        $actor=$this->writerManagementGuard(); $writerId=(int)$id;
        $writer=Database::one("SELECT u.*,p.professional_title,p.cv_summary,p.location,p.website,p.linkedin,p.portfolio_url,p.contact_email,p.is_public,p.show_contact FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id WHERE u.id=? AND u.role IN ('writer','trusted_writer') LIMIT 1",[$writerId]);
        if(!$writer){http_response_code(404);View::render('errors/404',['locale'=>default_locale(),'title'=>'الكاتب غير موجود'],'public');return;}
        $articles=Database::all("SELECT a.id,a.status,a.views,a.published_at,a.created_at,a.updated_at,(SELECT title FROM article_translations t WHERE t.article_id=a.id ORDER BY (t.locale=?) DESC,t.id LIMIT 1) title FROM articles a WHERE a.author_id=? ORDER BY a.updated_at DESC LIMIT 100",[default_locale(),$writerId]);
        $stats=Database::one("SELECT COUNT(*) total,SUM(status='published') published,SUM(status='draft') drafts,SUM(status='review') review,COALESCE(SUM(views),0) views,MAX(updated_at) last_article_at FROM articles WHERE author_id=?",[$writerId])?:[];
        $lastMessage=null; try{$lastMessage=Database::one('SELECT MAX(m.created_at) last_at FROM editorial_messages m JOIN editorial_threads t ON t.id=m.thread_id WHERE t.writer_id=?',[$writerId]);}catch(\Throwable){}
        $activity=[]; try{$activity=Database::all('SELECT action,target_type,target_id,details,created_at FROM audit_logs WHERE user_id=? ORDER BY id DESC LIMIT 25',[$writerId]);}catch(\Throwable){}
        $dates=array_filter([(string)($writer['last_login_at']??''),(string)($stats['last_article_at']??''),(string)($lastMessage['last_at']??'')]);
        $lastActivity=''; foreach($dates as $d){if($lastActivity===''||strtotime($d)>strtotime($lastActivity))$lastActivity=$d;}
        View::render('admin/writer-detail',compact('actor','writer','articles','stats','activity','lastMessage','lastActivity')+['title'=>'ملف الكاتب: '.$writer['name']],'dashboard');
    }

    public function impersonateWriter(): void {
        $actor=$this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['user_id']??0);
        $target=Database::one("SELECT id,name,username FROM users WHERE id=? AND role IN ('writer','trusted_writer') AND status='active'",[$id]);
        if(!$target){flash('error','حساب الكاتب غير موجود أو غير مفعل.');redirect('/admin/users');}
        audit_log('admin_impersonate_start','user',$id,'Admin #'.(int)$actor['id'].' entered writer workspace');
        if(!Auth::startImpersonation($id)){flash('error','تعذر الدخول إلى حساب الكاتب.');redirect('/admin/writers/'.$id);}
        redirect('/writer');
    }

    /** AJAX-only deterministic editorial review. It neither saves nor publishes the draft. */
    public function editorialCheck(): void {
        $this->contentGuard(); Csrf::enforce();
        header('Content-Type: application/json; charset=utf-8');
        try { echo json_encode((new \App\Services\EditorialQualityChecker())->inspect($_POST),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
        catch(\Throwable $e){ http_response_code(422); echo json_encode(['ok'=>false,'message'=>'تعذر إجراء الفحص التحريري.'],JSON_UNESCAPED_UNICODE); }
    }
    public function bulkArticles(): void {
        $this->contentGuard(); Csrf::enforce();
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['article_ids']??[])),static fn($id)=>$id>0)));
        $status=(string)($_POST['bulk_action']??'');
        if(!$ids || !in_array($status,['published','draft','archived'],true)){ flash('error','اختر مقالاً واحداً على الأقل وإجراءً صالحاً.'); redirect('/admin/articles'); }
        if($status==='published'){
            $ids=array_values(array_filter($ids,static fn(int $articleId): bool => article_publication_ready($articleId)));
            if(!$ids){ flash('error','لا توجد مقالات مكتملة ومُعتمدة وجاهزة للنشر.'); redirect('/admin/articles'); }
        }
        $marks=implode(',',array_fill(0,count($ids),'?'));
        Database::exec("UPDATE articles SET status=?,published_at=CASE WHEN ?='published' THEN COALESCE(published_at,CURRENT_TIMESTAMP) ELSE published_at END,updated_at=CURRENT_TIMESTAMP WHERE id IN ($marks)",array_merge([$status,$status],$ids));
        audit_log('article_bulk_status','article',null,$status.'|'.count($ids)); flash('success','تم تحديث '.count($ids).' مقالاً.'); redirect('/admin/articles');
    }
    public function toggleArticleVisibility(): void {
        $this->contentGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $article=Database::one('SELECT id,status FROM articles WHERE id=?',[$id]);
        if(!$article || !in_array($article['status'],['published','archived'],true)){ flash('error','المقال غير متاح لتغيير الظهور.'); redirect('/admin/articles'); }
        $next=$article['status']==='published'?'archived':'published';
        if($next==='published' && !article_publication_ready($id)){ flash('error','لا يمكن إظهار المقال قبل اكتمال واعتماد النسخة العربية المطلوبة.'); redirect('/admin/articles'); }
        Database::exec("UPDATE articles SET status=?,published_at=CASE WHEN ?='published' THEN COALESCE(published_at,CURRENT_TIMESTAMP) ELSE published_at END,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$next,$next,$id]);
        audit_log('article_visibility','article',$id,$next); flash('success',$next==='published'?'أصبح المقال ظاهراً في الموقع.':'تم إخفاء المقال من الموقع العام.'); redirect('/admin/articles');
    }
    public function emailTemplates(): void {
        $this->adminGuard();
        try{$templates=Database::all('SELECT * FROM email_notification_templates ORDER BY event_key');$attempts=Database::all('SELECT * FROM email_delivery_attempts ORDER BY id DESC LIMIT 100');}catch(\Throwable){$templates=[];$attempts=[];}
        View::render('admin/email-templates',compact('templates','attempts')+['title'=>'قوالب البريد'],'dashboard');
    }
    public function saveEmailTemplate(): void {
        $this->adminGuard(); Csrf::enforce();
        $key=preg_replace('/[^a-z0-9_-]/i','',(string)($_POST['event_key']??''));$subject=mb_substr(trim((string)($_POST['subject']??'')),0,255);$html=(string)($_POST['body_html']??'');$text=(string)($_POST['body_text']??'');
        if($key===''||$subject===''||trim($text)===''){flash('error','أكمل اسم الحدث والعنوان والنص البديل.');redirect('/admin/email-templates');}
        Database::exec('INSERT INTO email_notification_templates(event_key,subject,body_html,body_text,enabled) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE subject=VALUES(subject),body_html=VALUES(body_html),body_text=VALUES(body_text),enabled=VALUES(enabled)',[$key,$subject,$html,$text,isset($_POST['enabled'])?1:0]);
        audit_log('email_template_save','email_template',null,$key);flash('success','تم حفظ قالب البريد.');redirect('/admin/email-templates');
    }
    public function retryEmailAttempt(): void {
        $this->adminGuard(); Csrf::enforce();$id=(int)($_POST['id']??0);$attempt=Database::one('SELECT * FROM email_delivery_attempts WHERE id=?',[$id]);
        if(!$attempt){flash('error','محاولة الإرسال غير موجودة.');redirect('/admin/email-templates');}
        $template=Database::one('SELECT body_text FROM email_notification_templates WHERE event_key=? AND enabled=1',[(string)$attempt['event_key']]);
        $sent=send_logged_site_mail((string)$attempt['event_key'],(string)$attempt['recipient'],(string)$attempt['subject'],(string)($template['body_text']??'رسالة معاد إرسالها من إدارة سراديب.'),(string)($attempt['related_type']??''),(int)($attempt['related_id']??0));
        audit_log('email_attempt_retry','email_delivery_attempt',$id,$sent?'accepted':'failed');flash($sent?'success':'error',$sent?'تمت إعادة محاولة الإرسال.':'تعذرت إعادة محاولة الإرسال.');redirect('/admin/email-templates');
    }
    public function checkWriterApplicationUsername(): void {
        $this->writerManagementGuard();header('Content-Type: application/json; charset=utf-8');$username=normalize_writer_username((string)($_GET['username']??''));
        if(!valid_writer_username($username)){echo json_encode(['available'=>false,'message'=>'اسم المستخدم غير صالح.'],JSON_UNESCAPED_UNICODE);return;}
        $busy=(bool)Database::one('SELECT id FROM users WHERE username=? LIMIT 1',[$username]);if(!$busy){try{$busy=(bool)Database::one('SELECT user_id FROM writer_username_redirects WHERE old_username=? LIMIT 1',[$username]);}catch(\Throwable){}}
        echo json_encode(['available'=>!$busy,'message'=>$busy?'اسم المستخدم محجوز.':'اسم المستخدم متاح.'],JSON_UNESCAPED_UNICODE);
    }
    public function addWriterApplicationNote(): void {
        $u=$this->writerManagementGuard();Csrf::enforce();$id=(int)($_POST['id']??0);$body=trim((string)($_POST['note']??''));if($id<1||$body===''){flash('error','اكتب ملاحظة داخلية أولاً.');redirect('/admin/writer-applications');}
        try{Database::insert('INSERT INTO writer_application_notes(application_id,author_id,body) VALUES(?,?,?)',[$id,(int)$u['id'],$body]);audit_log('writer_application_note','writer_application',$id,'internal');flash('success','تم حفظ الملاحظة الداخلية.');}catch(\Throwable){flash('error','تعذر حفظ الملاحظة الداخلية.');}redirect('/admin/writer-applications');
    }
    public function sendWriterApplicationMessage(): void {
        $u=$this->writerManagementGuard();Csrf::enforce();$id=(int)($_POST['id']??0);$body=trim((string)($_POST['message']??''));if($id<1||$body===''){flash('error','اكتب الرسالة أولاً.');redirect('/admin/writer-applications');}
        try{Database::insert("INSERT INTO writer_application_messages(application_id,sender_id,visibility,body) VALUES(?,?,'applicant',?)",[$id,(int)$u['id'],$body]);flash('success','تم إرسال الرسالة إلى صاحب الطلب.');}catch(\Throwable){flash('error','تعذر إرسال الرسالة.');}redirect('/admin/writer-applications');
    }
    public function reopenWriterApplication(): void {
        $u=$this->writerManagementGuard();Csrf::enforce();$id=(int)($_POST['id']??0);$reason=trim((string)($_POST['reason']??''));$app=Database::one('SELECT user_id,status FROM writer_applications WHERE id=?',[$id]);if(!$app||$reason===''){flash('error','أدخل سبب إعادة فتح الطلب.');redirect('/admin/writer-applications');}
        Database::transaction(function()use($app,$id,$reason,$u){Database::exec("UPDATE writer_applications SET status='pending',admin_note=? WHERE id=?",[$reason,$id]);if(!empty($app['user_id']))Database::exec("UPDATE users SET role='member',status='pending' WHERE id=?",[(int)$app['user_id']]);try{Database::insert('INSERT INTO writer_application_history(application_id,from_status,to_status,admin_id,note) VALUES(?,?,?,?,?)',[$id,$app['status'],'pending',(int)$u['id'],$reason]);}catch(\Throwable){}});flash('success','أعيد فتح طلب العضوية.');redirect('/admin/writer-applications');
    }
    public function reactivateWriterApplication(): void {
        $this->writerManagementGuard();Csrf::enforce();$id=(int)($_POST['id']??0);$app=Database::one('SELECT user_id FROM writer_applications WHERE id=?',[$id]);if(!$app){flash('error','الطلب غير موجود.');redirect('/admin/writer-applications');}
        Database::transaction(function()use($app,$id){Database::exec("UPDATE writer_applications SET status='under_review' WHERE id=?",[$id]);if(!empty($app['user_id']))Database::exec("UPDATE users SET status='active' WHERE id=?",[(int)$app['user_id']]);});flash('success','تمت إعادة تفعيل الحساب وإحالته للمراجعة.');redirect('/admin/writer-applications');
    }
    public function remindWriterApplication(): void {
        $this->writerManagementGuard();Csrf::enforce();$id=(int)($_POST['id']??0);$app=Database::one('SELECT name,email FROM writer_applications WHERE id=?',[$id]);if(!$app){flash('error','الطلب غير موجود.');redirect('/admin/writer-applications');}
        $sent=send_logged_site_mail('writer_application_reminder',(string)$app['email'],'تحديث طلب عضوية الكاتب في سراديب','مرحبًا '.(string)$app['name']."،\n\nطلب عضويتك ما زال قيد المراجعة. يمكنك تسجيل الدخول لاستكمال ملفك ومتابعة حالته.",'writer_application',$id);try{Database::exec('UPDATE writer_applications SET last_reminded_at=CURRENT_TIMESTAMP,reminder_count=COALESCE(reminder_count,0)+1 WHERE id=?',[$id]);}catch(\Throwable){}flash($sent?'success':'error',$sent?'تم إرسال التذكير.':'تعذر إرسال التذكير.');redirect('/admin/writer-applications');
    }
    public function importForm(): void { $this->adminGuard(); View::render('admin/import', ['title'=>'استيراد WordPress'], 'dashboard'); }
    public function importWordpress(): void {
        $this->adminGuard(); Csrf::enforce();
        if(empty($_FILES['wxr']['tmp_name'])){flash('error','اختر ملف WXR XML.');redirect('/admin/import-wordpress');}
        require_once BASE_PATH.'/tools/WordpressImporter.php';
        try { $result=(new \App\Tools\WordpressImporter())->import($_FILES['wxr']['tmp_name'],(int)Auth::user()['id']); flash('success','تم الاستيراد: '.$result['posts'].' مقال، '.$result['pages'].' صفحة، و'.$result['categories'].' تصنيف.'); }
        catch(\Throwable $e){ flash('error','تعذر الاستيراد: '.$e->getMessage()); }
        redirect('/admin/import-wordpress');
    }
}
