<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;

final class KhawatirController {
    private function writerGuard(): array {
        Auth::requireRole(['admin','editor','writer','trusted_writer']);
        $user=Auth::user();
        if(($user['status']??'')!=='active'){ http_response_code(403); exit('الحساب غير مفعل.'); }
        return $user;
    }

    private function editorGuard(): array {
        Auth::requireRole(['admin','editor']);
        return Auth::user();
    }

    private function adminGuard(): array {
        Auth::requireRole(['admin']);
        return Auth::user();
    }

    private function categories(bool $all=false): array {
        return Database::all('SELECT * FROM khawatir_categories'.($all?'':' WHERE active=1').' ORDER BY sort_order,id');
    }

    private function locale(string $locale): string {
        return valid_locale($locale)?$locale:default_locale();
    }

    private function thought(string $id,array $user): ?array {
        $thought=Database::one('SELECT k.*,c.name category_name,c.slug category_slug,u.name author_name,u.username,u.avatar author_avatar FROM khawatir k JOIN users u ON u.id=k.author_id LEFT JOIN khawatir_categories c ON c.id=k.category_id WHERE k.id=?',[(int)$id]);
        if(!$thought || (!is_editor() && (int)$thought['author_id']!==(int)$user['id'])) return null;
        return $thought;
    }

    public function writerIndex(): void {
        $user=$this->writerGuard();
        $status=(string)($_GET['status']??''); $params=[(int)$user['id']];
        $where=' WHERE k.author_id=?';
        if(in_array($status,['draft','review','changes','rejected','published'],true)){ $where.=' AND k.status=?'; $params[]=$status; }
        $thoughts=Database::all('SELECT k.*,c.name category_name FROM khawatir k LEFT JOIN khawatir_categories c ON c.id=k.category_id'.$where.' ORDER BY k.updated_at DESC',$params);
        View::render('writer/khawatir',compact('user','thoughts','status')+['title'=>'خواطري'],'dashboard');
    }

    public function edit(?string $id=null): void {
        $user=$this->writerGuard(); $thought=null;
        if($id && $id!=='new'){
            $thought=$this->thought($id,$user);
            if(!$thought){ http_response_code(403); exit('غير مصرح.'); }
        }
        $categories=$this->categories();
        $maxChars=max(120,min(360,(int)setting('khawatir_max_chars','360')));
        $warnChars=max(80,min($maxChars-20,(int)setting('khawatir_warn_chars','260')));
        View::render('writer/khawatir-editor',compact('user','thought','categories','maxChars','warnChars')+['title'=>$thought?'تعديل خاطرة':'أضف خاطرة'],'dashboard');
    }

    public function save(): void { $this->persist(false); }
    public function submit(): void { $this->persist(true); }

    private function persist(bool $submit): void {
        $user=$this->writerGuard(); Csrf::enforce();
        if(!is_editor() && Auth::writerPrivilegesSuspended($user)){ flash('error','صلاحيات الكاتب معلقة مؤقتًا حتى '.(string)$user['writer_suspended_until'].'.'); redirect('/writer/khawatir'); }
        $id=(int)($_POST['id']??0); $thought=$id?$this->thought((string)$id,$user):null;
        if($id && !$thought){ http_response_code(403); exit('غير مصرح.'); }
        if($thought && !is_editor() && !in_array($thought['status'],['draft','changes','rejected'],true)){ flash('error','لا يمكن تعديل الخاطرة أثناء المراجعة أو بعد النشر.'); redirect('/writer/khawatir/'.$id); }

        $maxChars=max(120,min(360,(int)setting('khawatir_max_chars','360')));
        $body=str_replace(["\r\n","\r"],"\n",strip_tags((string)($_POST['body']??'')));
        $body=preg_replace('/[\t ]+/u',' ',$body)??''; $body=preg_replace('/\n{3,}/u',"\n\n",$body)??''; $body=trim($body);
        if($body==='' || mb_strlen($body)>$maxChars){ flash('error','اكتب نصاً لا يتجاوز '.$maxChars.' حرفاً.'); redirect($id?'/writer/khawatir/'.$id:'/writer/khawatir/new'); }
        $categoryId=(int)($_POST['category_id']??0);
        if(!Database::one('SELECT id FROM khawatir_categories WHERE id=? AND active=1',[$categoryId])) $categoryId=null;
        if($submit && !$categoryId){ flash('error','حدد تصنيف الخاطرة قبل إرسالها للمراجعة أو نشرها.'); redirect($id?'/writer/khawatir/'.$id:'/writer/khawatir/new'); }
        $ownership=($_POST['ownership']??'original')==='quoted'?'quoted':'original';
        $originalAuthor=mb_substr(trim((string)($_POST['original_author']??'')),0,190);
        $sourceName=mb_substr(trim((string)($_POST['source_name']??'')),0,255);
        $sourceUrl=trim((string)($_POST['source_url']??''));
        if($sourceUrl!=='' && (!filter_var($sourceUrl,FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($sourceUrl,PHP_URL_SCHEME)),['http','https'],true))) $sourceUrl='';
        if($ownership==='quoted' && $originalAuthor===''){ flash('error','اسم صاحب النص الأصلي مطلوب للنص المنقول أو المقتبس.'); redirect($id?'/writer/khawatir/'.$id:'/writer/khawatir/new'); }
        if($ownership==='original'){ $originalAuthor=''; $sourceName=''; $sourceUrl=''; }
        $category=$categoryId?Database::one('SELECT template_key,template_active FROM khawatir_categories WHERE id=?',[$categoryId]):null;
        $templateKey=!empty($_POST['long_template'])?'template_long':((!empty($category['template_active'])&&preg_match('/^template_[a-z_]+$/',$category['template_key']??''))?$category['template_key']:'template_khawatira');
        $sizeMode=in_array($_POST['text_size_mode']??'', ['auto','small','medium','large'],true)?$_POST['text_size_mode']:'auto';
        $variant=preg_match('/^[a-z0-9_-]{1,40}$/i',(string)($_POST['template_variant']??''))?(string)$_POST['template_variant']:'default';
        $alignment=in_array($_POST['text_alignment']??'', ['center','right'],true)?$_POST['text_alignment']:'center';
        $emphasis=($_POST['text_emphasis']??'normal')==='strong'?'strong':'normal';

        $status=$submit?(is_editor()?'published':'review'):($thought['status']??'draft');
        if(!$submit && !is_editor())$status='draft';
        $publishedAt=$status==='published'?($thought['published_at']??date('Y-m-d H:i:s')):($thought['published_at']??null);
        if(!$id){
            $slug=slugify(mb_substr($body,0,70)).'-'.bin2hex(random_bytes(3));
            $id=Database::insert('INSERT INTO khawatir(author_id,category_id,template_key,template_version,text_alignment,text_emphasis,text_size_mode,template_variant,body,slug,ownership,original_author,source_name,source_url,image_path,status,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,?)',[(int)$user['id'],$categoryId,$templateKey,1,$alignment,$emphasis,$sizeMode,$variant,$body,$slug,$ownership,$originalAuthor?:null,$sourceName?:null,$sourceUrl?:null,$status,$publishedAt]);
        }else{
            Database::exec('UPDATE khawatir SET category_id=?,template_key=?,template_version=1,text_alignment=?,text_emphasis=?,text_size_mode=?,template_variant=?,body=?,ownership=?,original_author=?,source_name=?,source_url=?,image_path=NULL,status=?,published_at=?,review_note=CASE WHEN ?="review" THEN NULL ELSE review_note END,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$categoryId,$templateKey,$alignment,$emphasis,$sizeMode,$variant,$body,$ownership,$originalAuthor?:null,$sourceName?:null,$sourceUrl?:null,$status,$publishedAt,$status,$id]);
        }
        if($submit && !is_editor()){
            try{ foreach(Database::all("SELECT id FROM users WHERE role IN ('admin','editor') AND status='active'") as $editor) Database::insert('INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)',[(int)$editor['id'],'khawatir_review','خاطرة بانتظار المراجعة','أرسل الكاتب خاطرة جديدة للمراجعة.','/admin/khawatir/'.$id]); }catch(\Throwable){}
        }
        audit_log($submit?'khawatir_submit':'khawatir_save','khawatir',$id,$status);
        flash('success',$status==='review'?'تم إرسال الخاطرة للمراجعة.':($status==='published'?'تم نشر الخاطرة.':'تم حفظ الخاطرة.'));
        redirect('/writer/khawatir/'.$id);
    }

    public function delete(): void {
        $user=$this->writerGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $thought=$this->thought((string)$id,$user);
        if(!$thought || (!is_editor() && !in_array($thought['status'],['draft','changes','rejected'],true))){ flash('error','لا يمكن حذف هذه الخاطرة.'); redirect('/writer/khawatir'); }
        Database::exec('DELETE FROM khawatir WHERE id=?',[$id]); audit_log('khawatir_delete','khawatir',$id,'');
        flash('success','تم حذف الخاطرة.'); redirect(is_editor()?'/admin/khawatir':'/writer/khawatir');
    }

    public function adminIndex(): void {
        $this->editorGuard(); $q=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??''); $category=(int)($_GET['category']??0);
        $where=[];$params=[];
        if($q!==''){ $where[]='(k.body LIKE ? OR u.name LIKE ? OR u.username LIKE ?)'; $like='%'.$q.'%'; array_push($params,$like,$like,$like); }
        if(in_array($status,['draft','review','changes','rejected','published'],true)){ $where[]='k.status=?';$params[]=$status; }
        if($category){$where[]='k.category_id=?';$params[]=$category;}
        $thoughts=Database::all('SELECT k.*,u.name author_name,u.username,c.name category_name FROM khawatir k JOIN users u ON u.id=k.author_id LEFT JOIN khawatir_categories c ON c.id=k.category_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY FIELD(k.status,"review","changes","draft","rejected","published"),k.updated_at DESC LIMIT 300',$params);
        $pendingCount=(int)(Database::one("SELECT COUNT(*) c FROM khawatir WHERE status='review'")['c']??0);
        $categories=$this->categories(true); $filters=compact('q','status','category');
        View::render('admin/khawatir',compact('thoughts','categories','filters','pendingCount')+['title'=>'خواطر'],'dashboard');
    }

    public function adminEdit(string $id): void { $this->editorGuard(); redirect('/writer/khawatir/'.(int)$id); }

    public function review(): void {
        $reviewer=$this->editorGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'');
        $status=match($action){'publish'=>'published','changes'=>'changes','reject'=>'rejected',default=>'review'};
        $categoryId=(int)($_POST['category_id']??0);
        if(!Database::one('SELECT id FROM khawatir_categories WHERE id=? AND active=1',[$categoryId])){ flash('error','حدد تصنيفاً صالحاً قبل اتخاذ قرار النشر.'); redirect('/writer/khawatir/'.$id); }
        $note=mb_substr(trim((string)($_POST['review_note']??'')),0,3000);
        $publishedAt=trim((string)($_POST['published_at']??''));
        $publishedAt=$publishedAt!==''?date('Y-m-d H:i:s',strtotime($publishedAt)):date('Y-m-d H:i:s');
        $thought=Database::one('SELECT k.*,u.email,u.name FROM khawatir k JOIN users u ON u.id=k.author_id WHERE k.id=?',[$id]);
        if(!$thought){ flash('error','الخاطرة غير موجودة.'); redirect('/admin/khawatir'); }
        Database::exec('UPDATE khawatir SET category_id=?,status=?,review_note=?,reviewed_by=?,published_at=CASE WHEN ?="published" THEN ? ELSE published_at END,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$categoryId,$status,$note?:null,(int)$reviewer['id'],$status,$publishedAt,$id]);
        // A published reflection must have publishable localized copies; otherwise it vanishes from the public archive.
        if($status==='published') Database::exec("UPDATE khawatir_translations SET status='published' WHERE khawatir_id=?",[$id]);
        $titles=['published'=>'تم نشر خاطرتك','changes'=>'خاطرتك تحتاج تعديلاً','rejected'=>'تعذر نشر الخاطرة','review'=>'الخاطرة قيد المراجعة'];
        $message=$note!==''?$note:($status==='published'?'وافقت الإدارة على الخاطرة وتم نشرها.':'راجع حالة الخاطرة في حسابك.');
        try{Database::insert('INSERT INTO notifications(user_id,type,title,message,link) VALUES(?,?,?,?,?)',[(int)$thought['author_id'],'khawatir_'.$status,$titles[$status],$message,'/writer/khawatir/'.$id]);}catch(\Throwable){}
        audit_log('khawatir_review','khawatir',$id,$status); flash('success','تم تحديث حالة الخاطرة.'); redirect('/admin/khawatir');
    }

    public function saveCategory(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $name=mb_substr(trim((string)($_POST['name']??'')),0,100);
        if($name===''){flash('error','اسم التصنيف مطلوب.');redirect('/admin/khawatir');}
        $slug=slugify((string)($_POST['slug']??$name)); $order=(int)($_POST['sort_order']??0); $active=isset($_POST['active'])?1:0; $templateActive=isset($_POST['template_active'])?1:0;
        $templateKey=preg_match('/^template_[a-z_]+$/',(string)($_POST['template_key']??''))?(string)$_POST['template_key']:'template_khawatira';
        $duplicate=Database::one('SELECT id FROM khawatir_categories WHERE slug=? AND id<>?',[$slug,$id]);
        if($duplicate){flash('error','رابط التصنيف مستخدم.');redirect('/admin/khawatir');}
        if($id)Database::exec('UPDATE khawatir_categories SET name=?,slug=?,template_key=?,template_active=?,sort_order=?,active=? WHERE id=?',[$name,$slug,$templateKey,$templateActive,$order,$active,$id]);
        else Database::insert('INSERT INTO khawatir_categories(name,slug,template_key,template_active,sort_order,active) VALUES(?,?,?,?,?,?)',[$name,$slug,$templateKey,$templateActive,$order,$active]);
        flash('success','تم حفظ التصنيف.');redirect('/admin/khawatir');
    }

    public function saveSettings(): void {
        $this->adminGuard(); Csrf::enforce();
        $max=max(120,min(360,(int)($_POST['khawatir_max_chars']??360))); $warn=max(80,min($max-20,(int)($_POST['khawatir_warn_chars']??260)));
        $values=['khawatir_max_chars'=>(string)$max,'khawatir_warn_chars'=>(string)$warn];
        foreach(['khawatir_enabled','khawatir_show_qr','khawatir_show_username','khawatir_show_site_url','khawatir_allow_download','khawatir_allow_share'] as $key)$values[$key]=isset($_POST[$key])?'1':'0';
        foreach($values as $key=>$value)Database::exec('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]);
        flash('success','تم حفظ إعدادات خواطر.');redirect('/admin/khawatir');
    }

    public function index(string $locale): void {
        $locale=$this->locale($locale); if($locale!=='ar'){redirect(locale_url('ar','khawatir'),302);return;} if(setting('khawatir_enabled','1')!=='1'){http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');return;}
        $writerNameSql=($locale==='ar'||translation_fallback_enabled($locale))?"COALESCE(NULLIF(wt.display_name,''),u.name)":"COALESCE(NULLIF(wt.display_name,''),CASE WHEN u.username REGEXP '^[A-Za-z0-9_-]+$' THEN u.username ELSE CONCAT('Writer ',u.id) END)";
        $category=trim((string)($_GET['category']??'')); $q=trim((string)($_GET['q']??''));
        $where=["k.status='published'","kt.status='published'","k.published_at<=CURRENT_TIMESTAMP"];$params=[$locale,$locale,$locale];
        if($category!==''){ $where[]='kc.slug=?';$params[]=$category; }
        if(mb_strlen($q)>=2){$where[]="(kt.body LIKE ? OR {$writerNameSql} LIKE ? OR k.original_author LIKE ?)";$like='%'.$q.'%';array_push($params,$like,$like,$like);}
        $thoughts=Database::all("SELECT k.*,kt.title,kt.slug,kt.body,kt.description,NULL image_path,kc.name category_name,kc.slug category_slug,{$writerNameSql} author_name,u.username,u.avatar author_avatar FROM khawatir k JOIN khawatir_translations kt ON kt.khawatir_id=k.id AND kt.locale=? JOIN users u ON u.id=k.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? AND wt.status='published' LEFT JOIN khawatir_category_translations kc ON kc.category_id=k.category_id AND kc.locale=? AND kc.status='published' WHERE ".implode(' AND ',$where).' ORDER BY k.published_at DESC,k.id DESC LIMIT 80',$params);
        $meta=[
            'ar'=>['title'=>'خواطر','description'=>'نصوص قصيرة وتأملات واقتباسات مختارة من كتّاب سراديب.'],
            'en'=>['title'=>'Reflections','description'=>'Short texts, thoughts and selected quotations from Saradeeeb writers.'],
            'es'=>['title'=>'Reflexiones','description'=>'Textos breves, pensamientos y citas seleccionadas de autores de Saradeeeb.'],
            'fr'=>['title'=>'Réflexions','description'=>'Textes courts, pensées et citations choisies des auteurs de Saradeeeb.'],
            'ru'=>['title'=>'Размышления','description'=>'Короткие тексты, мысли и избранные цитаты авторов Saradeeeb.'],
        ][$locale]??['title'=>'Reflections','description'=>'Short texts, thoughts and selected quotations from Saradeeeb writers.'];
        $categories=Database::all("SELECT c.id,ct.name,ct.slug,ct.description FROM khawatir_categories c JOIN khawatir_category_translations ct ON ct.category_id=c.id AND ct.locale=? AND ct.status='published' WHERE c.active=1 ORDER BY c.sort_order,c.id",[$locale]);
        $title=$meta['title']; $description=$meta['description'];
        View::render('public/khawatir',compact('locale','thoughts','categories','category','q','title','description'),'public');
    }

    public function show(string $locale,string $slug): void {
        $locale=$this->locale($locale); if($locale!=='ar'){redirect(locale_url('ar','khawatir'),302);return;} if(setting('khawatir_enabled','1')!=='1'){http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');return;}
        $writerNameSql=($locale==='ar'||translation_fallback_enabled($locale))?"COALESCE(NULLIF(wt.display_name,''),u.name)":"COALESCE(NULLIF(wt.display_name,''),CASE WHEN u.username REGEXP '^[A-Za-z0-9_-]+$' THEN u.username ELSE CONCAT('Writer ',u.id) END)";
        $writerBioSql=($locale==='ar'||translation_fallback_enabled($locale))?"COALESCE(NULLIF(wt.bio,''),u.bio)":"NULLIF(wt.bio,'')";
        $thought=Database::one("SELECT k.*,kt.title,kt.slug,kt.body,kt.description,kt.seo_title,kt.seo_description,NULL image_path,kc.name category_name,kc.slug category_slug,{$writerNameSql} author_name,u.username,u.avatar author_avatar,{$writerBioSql} author_bio FROM khawatir k JOIN khawatir_translations kt ON kt.khawatir_id=k.id AND kt.locale=? AND kt.status='published' JOIN users u ON u.id=k.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? AND wt.status='published' LEFT JOIN khawatir_category_translations kc ON kc.category_id=k.category_id AND kc.locale=? AND kc.status='published' WHERE kt.slug=? AND k.status='published' AND k.published_at<=CURRENT_TIMESTAMP LIMIT 1",[$locale,$locale,$locale,$slug]);
        if(!$thought && translation_fallback_enabled($locale)) $thought=Database::one("SELECT k.*,kt.title,kt.slug,kt.body,kt.description,kt.seo_title,kt.seo_description,NULL image_path,kc.name category_name,kc.slug category_slug,u.name author_name,u.username,u.avatar author_avatar,u.bio author_bio FROM khawatir k JOIN khawatir_translations kt ON kt.khawatir_id=k.id AND kt.locale='ar' AND kt.status='published' JOIN users u ON u.id=k.author_id LEFT JOIN khawatir_category_translations kc ON kc.category_id=k.category_id AND kc.locale='ar' WHERE kt.slug=? AND k.status='published' AND k.published_at<=CURRENT_TIMESTAMP LIMIT 1",[$slug]);
        if(!$thought){http_response_code(404);View::render('errors/404',compact('locale')+['title'=>'404'],'public');return;}
        Database::exec('UPDATE khawatir SET views=views+1 WHERE id=?',[$thought['id']]);$thought['views']=(int)$thought['views']+1;
        $cardSelect="SELECT k.*,kt.title,kt.slug,kt.body,kc.name category_name,{$writerNameSql} author_name,u.username,u.avatar author_avatar FROM khawatir k JOIN khawatir_translations kt ON kt.khawatir_id=k.id AND kt.locale=? AND kt.status='published' LEFT JOIN khawatir_category_translations kc ON kc.category_id=k.category_id AND kc.locale=? AND kc.status='published' JOIN users u ON u.id=k.author_id LEFT JOIN writer_profile_translations wt ON wt.user_id=u.id AND wt.locale=? AND wt.status='published'";
        $more=Database::all($cardSelect." WHERE k.author_id=? AND k.id<>? AND k.status='published' AND k.published_at<=CURRENT_TIMESTAMP ORDER BY k.published_at DESC LIMIT 3",[$locale,$locale,$locale,$thought['author_id'],$thought['id']]);
        $related=Database::all($cardSelect." WHERE k.category_id<=>? AND k.id<>? AND k.status='published' AND k.published_at<=CURRENT_TIMESTAMP ORDER BY k.published_at DESC LIMIT 3",[$locale,$locale,$locale,$thought['category_id'],$thought['id']]);
        $title=$thought['seo_title']?:($thought['title']?:mb_substr($thought['body'],0,70)); $description=$thought['seo_description']?:($thought['description']?:mb_substr($thought['body'],0,180)); $canonical=locale_url($locale,'khawatir/'.$thought['slug']);
        $ogImage=setting('brand_logo','')?:($thought['author_avatar']??'');
        View::render('public/khawatir-show',compact('locale','thought','more','related','title','description','canonical','ogImage'),'public');
    }
}
