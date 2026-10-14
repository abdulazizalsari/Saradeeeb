<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Upload;
use App\Core\View;
use App\Services\DatabaseBackup;
use App\Services\TranslationExchange;
use App\Services\TranslationCompletenessValidator;

final class AdminToolsController {
    private function admin(): array { Auth::requireRole(['admin']); return Auth::user(); }
    private function content(): array { Auth::requireRole(['admin','editor']); return Auth::user(); }

    public function languages(): void {
        $this->admin();
        $languages=Database::all('SELECT * FROM languages ORDER BY sort_order,id');
        $editLocale=(string)($_GET['edit']??default_locale()); if(!isset(all_locales()[$editLocale])) $editLocale=default_locale();
        $translationFields=translation_field_definitions(); $translationValues=[];
        try{ foreach(Database::all('SELECT namespace,text_key,value FROM translation_strings WHERE locale=?',[$editLocale]) as $r) $translationValues[$r['namespace'].'.'.$r['text_key']]=$r['value']; }catch(\Throwable){}
        $totalTranslationFields=count($translationFields); $languageStats=[];
        try {
            $articleTotal=(int)(Database::one("SELECT COUNT(*) c FROM articles WHERE status='published'")['c']??0);
            $pageTotal=(int)(Database::one("SELECT COUNT(*) c FROM pages WHERE status='published'")['c']??0);
            $categoryTotal=(int)(Database::one("SELECT COUNT(*) c FROM categories WHERE active=1")['c']??0);
            $podcastTotal=(int)(Database::one("SELECT COUNT(*) c FROM podcast_episodes WHERE status='published'")['c']??0);
            $khawatirTotal=(int)(Database::one("SELECT COUNT(*) c FROM khawatir WHERE status='published'")['c']??0);
            $khawatirCategoryTotal=(int)(Database::one("SELECT COUNT(*) c FROM khawatir_categories WHERE active=1")['c']??0);
            $writerTotal=(int)(Database::one("SELECT COUNT(DISTINCT user_id) c FROM writer_profile_translations WHERE locale=?",[default_locale()])['c']??0);
            $tagTotal=(int)(Database::one("SELECT COUNT(*) c FROM tags")['c']??0);
            foreach($languages as $language){
                $code=(string)$language['code'];
                $articleDone=(int)(Database::one("SELECT COUNT(DISTINCT t.article_id) c FROM article_translations t JOIN articles a ON a.id=t.article_id WHERE t.locale=? AND ".article_translation_publishable_sql('t')." AND a.status='published'",[$code])['c']??0);
                $pageDone=(int)(Database::one("SELECT COUNT(DISTINCT t.page_id) c FROM page_translations t JOIN pages p ON p.id=t.page_id WHERE t.locale=? AND p.status='published'",[$code])['c']??0);
                $categoryDone=(int)(Database::one("SELECT COUNT(DISTINCT t.category_id) c FROM category_translations t JOIN categories c ON c.id=t.category_id WHERE t.locale=? AND c.active=1",[$code])['c']??0);
                $stringsDone=(int)(Database::one('SELECT COUNT(*) c FROM translation_strings WHERE locale=? AND TRIM(COALESCE(value,""))<>""',[$code])['c']??0);
                $podcastDone=(int)(Database::one("SELECT COUNT(DISTINCT t.episode_id) c FROM podcast_episode_translations t JOIN podcast_episodes p ON p.id=t.episode_id WHERE t.locale=? AND t.status='published' AND p.status='published'",[$code])['c']??0);
                $khawatirDone=(int)(Database::one("SELECT COUNT(DISTINCT t.khawatir_id) c FROM khawatir_translations t JOIN khawatir k ON k.id=t.khawatir_id WHERE t.locale=? AND t.status='published' AND k.status='published'",[$code])['c']??0);
                $khawatirCategoryDone=(int)(Database::one("SELECT COUNT(DISTINCT t.category_id) c FROM khawatir_category_translations t JOIN khawatir_categories c ON c.id=t.category_id WHERE t.locale=? AND t.status='published' AND c.active=1",[$code])['c']??0);
                $writerDone=(int)(Database::one("SELECT COUNT(DISTINCT user_id) c FROM writer_profile_translations WHERE locale=? AND status='published'",[$code])['c']??0);
                $tagDone=(int)(Database::one("SELECT COUNT(DISTINCT tag_id) c FROM tag_translations WHERE locale=? AND status='published'",[$code])['c']??0);
                $contentTotal=$articleTotal+$pageTotal+$categoryTotal+$podcastTotal+$khawatirTotal+$khawatirCategoryTotal+$writerTotal+$tagTotal+$totalTranslationFields;
                $contentDone=$articleDone+$pageDone+$categoryDone+$podcastDone+$khawatirDone+$khawatirCategoryDone+$writerDone+$tagDone+$stringsDone;
                $languageStats[$code]=['articles'=>$articleDone,'article_total'=>$articleTotal,'pages'=>$pageDone,'page_total'=>$pageTotal,'categories'=>$categoryDone,'category_total'=>$categoryTotal,'podcasts'=>$podcastDone,'podcast_total'=>$podcastTotal,'khawatir'=>$khawatirDone,'khawatir_total'=>$khawatirTotal,'khawatir_categories'=>$khawatirCategoryDone,'khawatir_category_total'=>$khawatirCategoryTotal,'writers'=>$writerDone,'writer_total'=>$writerTotal,'tags'=>$tagDone,'tag_total'=>$tagTotal,'strings'=>$stringsDone,'string_total'=>$totalTranslationFields,'percent'=>$contentTotal?min(100,round(($contentDone/$contentTotal)*100)):100];
            }
        } catch(\Throwable) {}
        $filterLocale=(string)($_GET['filter_locale']??$editLocale); if(!isset(all_locales()[$filterLocale])) $filterLocale=$editLocale;
        $filterType=(string)($_GET['type']??'all'); if(!in_array($filterType,['all','articles','pages','categories','podcasts','khawatir','khawatir_categories','writers','tags'],true))$filterType='all';
        $filterState=(string)($_GET['state']??'all'); if(!in_array($filterState,['all','missing','draft','review','published'],true))$filterState='all';
        $translationQueue=[];
        try{
            $sources=[
                'articles'=>["SELECT a.id,ar.title source_title,t.id translation_id,t.status,t.review_state FROM articles a JOIN article_translations ar ON ar.article_id=a.id AND ar.locale=? LEFT JOIN article_translations t ON t.article_id=a.id AND t.locale=? WHERE a.status='published' ORDER BY a.id DESC",'/writer/articles/'],
                'pages'=>["SELECT p.id,ar.title source_title,t.id translation_id,t.status,t.review_state FROM pages p JOIN page_translations ar ON ar.page_id=p.id AND ar.locale=? LEFT JOIN page_translations t ON t.page_id=p.id AND t.locale=? WHERE p.status='published' ORDER BY p.id DESC",'/admin/pages/'],
                'categories'=>["SELECT c.id,ar.name source_title,t.id translation_id,t.status,NULL review_state FROM categories c JOIN category_translations ar ON ar.category_id=c.id AND ar.locale=? LEFT JOIN category_translations t ON t.category_id=c.id AND t.locale=? WHERE c.active=1 ORDER BY c.sort_order,c.id",'/admin/categories?edit='],
                'podcasts'=>["SELECT p.id,ar.title source_title,t.id translation_id,t.status,NULL review_state FROM podcast_episodes p JOIN podcast_episode_translations ar ON ar.episode_id=p.id AND ar.locale=? LEFT JOIN podcast_episode_translations t ON t.episode_id=p.id AND t.locale=? WHERE p.status='published' ORDER BY p.id DESC",'/admin/podcast/'],
                'khawatir'=>["SELECT k.id,LEFT(ar.body,120) source_title,t.id translation_id,t.status,NULL review_state FROM khawatir k JOIN khawatir_translations ar ON ar.khawatir_id=k.id AND ar.locale=? LEFT JOIN khawatir_translations t ON t.khawatir_id=k.id AND t.locale=? WHERE k.status='published' ORDER BY k.id DESC",'/writer/khawatir/'],
                'khawatir_categories'=>["SELECT c.id,ar.name source_title,t.id translation_id,t.status,NULL review_state FROM khawatir_categories c JOIN khawatir_category_translations ar ON ar.category_id=c.id AND ar.locale=? LEFT JOIN khawatir_category_translations t ON t.category_id=c.id AND t.locale=? WHERE c.active=1 ORDER BY c.sort_order,c.id",'/admin/khawatir'],
                'writers'=>["SELECT u.id,ar.display_name source_title,t.id translation_id,t.status,NULL review_state FROM users u JOIN writer_profile_translations ar ON ar.user_id=u.id AND ar.locale=? LEFT JOIN writer_profile_translations t ON t.user_id=u.id AND t.locale=? WHERE u.status='active' AND u.role IN ('writer','trusted_writer','editor','admin') ORDER BY u.id DESC",'/admin/writers'],
                'tags'=>["SELECT g.id,COALESCE(ar.name,g.name) source_title,t.id translation_id,t.status,NULL review_state FROM tags g LEFT JOIN tag_translations ar ON ar.tag_id=g.id AND ar.locale=? LEFT JOIN tag_translations t ON t.tag_id=g.id AND t.locale=? ORDER BY g.id DESC",'/admin/categories#tags'],
            ];
            foreach($sources as $type=>$source){
                if($filterType!=='all' && $filterType!==$type) continue;
                foreach(Database::all($source[0],[in_array($type,['articles','pages'],true)?'ar':default_locale(),$filterLocale]) as $row){
                    $state=empty($row['translation_id'])?'missing':((string)($row['review_state']??'')==='review'?'review':(string)($row['status']??'draft'));
                    if($filterState!=='all' && $filterState!==$state) continue;
                    $translationQueue[]=['type'=>$type,'id'=>(int)$row['id'],'title'=>(string)$row['source_title'],'state'=>$state,'url'=>$source[1].(int)$row['id']];
                }
            }
        }catch(\Throwable){}
        $fallbackEnabled=translation_fallback_enabled($editLocale) && $editLocale!==default_locale();
        $importHistory=[]; try{$importHistory=Database::all('SELECT i.*,u.name user_name FROM translation_imports i LEFT JOIN users u ON u.id=i.user_id ORDER BY i.id DESC LIMIT 15');}catch(\Throwable){}
        View::render('admin/languages',compact('languages','editLocale','translationFields','translationValues','languageStats','translationQueue','filterLocale','filterType','filterState','fallbackEnabled','importHistory')+['title'=>'إدارة اللغات والترجمة'],'dashboard');
    }
    public function saveLanguage(): void {
        $this->admin(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $code=strtolower(trim((string)($_POST['code']??'')));
        $name=trim((string)($_POST['name']??'')); $flag=trim((string)($_POST['flag']??'🌐'));
        $dir=(($_POST['dir']??'ltr')==='rtl')?'rtl':'ltr'; $enabled=isset($_POST['enabled'])?1:0; $sort=(int)($_POST['sort_order']??0); $isDefault=isset($_POST['is_default'])?1:0;
        if(!preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{2,8})?$/',$code) || $name===''){ flash('error','اكتب كود لغة صحيحًا واسم اللغة.'); redirect('/admin/languages'); }
        if($isDefault){ Database::exec('UPDATE languages SET is_default=0'); $enabled=1; }
        try{
            if($id) Database::exec('UPDATE languages SET code=?,name=?,flag=?,dir=?,enabled=?,is_default=?,sort_order=? WHERE id=?',[$code,$name,$flag,$dir,$enabled,$isDefault,$sort,$id]);
            else $id=Database::insert('INSERT INTO languages(code,name,flag,dir,enabled,is_default,sort_order) VALUES(?,?,?,?,?,?,?)',[$code,$name,$flag,$dir,$enabled,$isDefault,$sort]);
        }catch(\Throwable $e){ flash('error','تعذر حفظ اللغة؛ تأكد أن الكود غير مستخدم.'); redirect('/admin/languages'); }
        if($isDefault) $this->putSetting('default_locale',$code);
        audit_log('language_save','language',$id,$code); flash('success','تم حفظ اللغة. يمكنك الآن ترجمة واجهتها من مركز الترجمة أدناه.'); redirect('/admin/languages?edit='.rawurlencode($code).'#translation-center');
    }
    public function deleteLanguage(): void {
        $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $row=Database::one('SELECT * FROM languages WHERE id=?',[$id]); if(!$row) redirect('/admin/languages');
        if(!empty($row['is_default'])){ flash('error','لا يمكن حذف اللغة الأساسية. اجعل لغة أخرى أساسية أولًا.'); redirect('/admin/languages'); }
        Database::exec('DELETE FROM languages WHERE id=?',[$id]); audit_log('language_delete','language',$id,(string)$row['code']); flash('success','تم حذف اللغة من قائمة الموقع. الترجمات القديمة لم تُحذف.'); redirect('/admin/languages');
    }


    public function saveTranslations(): void {
        $this->admin(); Csrf::enforce();
        $locale=(string)($_POST['locale']??''); if(!isset(all_locales()[$locale])){ flash('error','اللغة غير موجودة.'); redirect('/admin/languages'); }
        $defs=translation_field_definitions(); $allowed=[]; foreach($defs as $d)$allowed[$d['namespace'].'.'.$d['key']]=$d;
        $values=is_array($_POST['translations']??null)?$_POST['translations']:[];
        foreach($values as $compound=>$value){
            if(!isset($allowed[$compound])) continue;
            $d=$allowed[$compound]; $value=trim((string)$value);
            if($value===''){ Database::exec('DELETE FROM translation_strings WHERE namespace=? AND text_key=? AND locale=?',[$d['namespace'],$d['key'],$locale]); continue; }
            Database::exec('INSERT INTO translation_strings(namespace,text_key,locale,value) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=CURRENT_TIMESTAMP',[$d['namespace'],$d['key'],$locale,mb_substr($value,0,10000)]);
        }
        $this->putSetting('translation_fallback_enabled',isset($_POST['translation_fallback_enabled'])?'1':'0');
        audit_log('translations_save','language',null,$locale); flash('success','تم حفظ ترجمة واجهة الموقع للغة '.language_name($locale).'.'); redirect('/admin/languages?edit='.rawurlencode($locale).'#translation-center');
    }

    public function exportTranslations(): void {
        $this->admin();
        $format=strtolower((string)($_GET['format']??'xlsx')); if(!in_array($format,['xlsx','csv','json'],true))$format='xlsx';
        $types=is_array($_GET['types']??null)?$_GET['types']:[(string)($_GET['type']??'all')];
        $locales=is_array($_GET['locales']??null)?array_map('strval',$_GET['locales']):[];
        $allowed=array_keys(all_locales()); $locales=array_values(array_intersect($allowed,$locales));
        $normalized=TranslationExchange::normalizeTypes($types);
        $source=$normalized && !array_diff($normalized,['article','page'])?'ar':default_locale(); $locales=array_values(array_filter($locales,fn($locale)=>$locale!==$source));
        if(!$locales)$locales=array_values(array_filter($allowed,fn($locale)=>$locale!==$source));
        $filter=(string)($_GET['filter']??'all'); if(!in_array($filter,['all','untranslated','review'],true))$filter='all';
        $rows=TranslationExchange::rows($types,$locales,$filter);
        if(!empty($_GET['template'])) foreach($rows as &$row) foreach($locales as $locale)$row['values'][$locale]='';
        unset($row);
        $path=tempnam(sys_get_temp_dir(),'saradeeeb_translation_'); if($path===false)throw new \RuntimeException('تعذر إنشاء ملف التصدير.');
        $final=$path.'.'.$format; @rename($path,$final);
        try{
            if($format==='xlsx')TranslationExchange::writeXlsx($rows,$locales,$final);
            elseif($format==='csv')TranslationExchange::writeCsv($rows,$locales,$final);
            else TranslationExchange::writeJson($rows,$locales,$final);
            audit_log('translations_export','translations',null,$format.' rows='.count($rows).' locales='.implode(',',$locales));
            $mime=['xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','csv'=>'text/csv; charset=utf-8','json'=>'application/json; charset=utf-8'][$format];
            $name='saradeeeb-translations-'.date('Ymd-His').'.'.$format;
            header('Content-Type: '.$mime); header('Content-Disposition: attachment; filename="'.$name.'"'); header('Content-Length: '.filesize($final)); header('X-Content-Type-Options: nosniff');
            readfile($final);
        }finally{@unlink($final);}
    }

    public function importTranslations(): void {
        $user=$this->admin(); Csrf::enforce();
        $file=$_FILES['translation_file']??null;
        if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){flash('error','اختر ملف ترجمة صالحًا.');redirect('/admin/languages#translation-exchange');}
        if((int)($file['size']??0)>20*1024*1024){flash('error','حجم الملف يتجاوز 20MB.');redirect('/admin/languages#translation-exchange');}
        $extension=strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION)); if(!in_array($extension,['xlsx','csv','json'],true)){flash('error','الصيغ المقبولة: XLSX وCSV وJSON.');redirect('/admin/languages#translation-exchange');}
        try{
            $parsed=TranslationExchange::parseFile((string)$file['tmp_name'],$extension); if(!$parsed)throw new \RuntimeException('الملف لا يحتوي صفوف ترجمة.'); if(count($parsed)>20000)throw new \RuntimeException('الملف يتجاوز الحد الآمن البالغ 20,000 صف.');
            $catalog=TranslationExchange::catalog(); $languageCodes=array_keys(all_locales()); $sourceLocale=default_locale();
            $seen=[]; $prepared=[]; $stats=['total'=>count($parsed),'new'=>0,'updated'=>0,'unknown'=>0,'errors'=>0,'warnings'=>0];
            foreach($parsed as $index=>$row){
                $number=$index+2; $rawKey=trim((string)($row['translation_key']??'')); $key=mb_substr($rawKey,0,255); $messages=[]; $state='valid'; $action='skip';
                if($rawKey!==$key){$state='error';$messages[]='مفتاح الترجمة أطول من الحد المسموح.';}
                if($key==='' || isset($seen[$key])){$state='error';$messages[]=$key===''?'مفتاح الترجمة مفقود.':'مفتاح مكرر داخل الملف.';}
                $seen[$key]=true; $meta=$catalog[$key]??null;
                if(!$meta){$state='unknown';$messages[]='المفتاح لا يطابق عنصرًا موجودًا.';$stats['unknown']++;}
                $translations=[];
                foreach($languageCodes as $locale){if($locale===($meta['source_locale']??$sourceLocale) || !array_key_exists($locale,$row))continue;$value=trim((string)$row[$locale]);if($value!=='')$translations[$locale]=$value;}
                if(!$translations && $state==='valid'){$state='error';$messages[]='لا توجد ترجمة غير فارغة في الصف.';}
                if($meta){
                    $fileHash=trim((string)($row['source_hash']??''));
                    if($fileHash==='' || !hash_equals((string)$meta['source_hash'],$fileHash)){$state=$state==='valid'?'warning':$state;$messages[]='النص الأصلي تغير منذ إنشاء هذا الملف.';$stats['warnings']++;}
                    $required=TranslationExchange::placeholders((string)$meta['source_text']);
                    foreach($translations as $locale=>$value){$present=TranslationExchange::placeholders($value);$missing=array_values(array_diff($required,$present));if($missing){$state='error';$messages[]='Placeholders مفقودة في '.$locale.': '.implode(', ',$missing);}}
                    if($translations){$newCells=0;$updatedCells=0;foreach($translations as $locale=>$value){if(trim((string)($meta['values'][$locale]??''))==='')$newCells++;else $updatedCells++;}$action=$updatedCells&&$newCells?'mixed':($updatedCells?'update':'new');if($state!=='error'){$stats['new']+=$newCells;$stats['updated']+=$updatedCells;}}
                }
                if($state==='error')$stats['errors']++;
                $prepared[]=['row_number'=>$number,'key'=>$key,'meta'=>$meta,'source_text'=>(string)($row['source_text']??''),'source_hash'=>mb_substr((string)($row['source_hash']??''),0,64),'translations'=>$translations,'action'=>$action,'state'=>$state,'messages'=>$messages];
            }
            $importId=Database::transaction(function()use($user,$file,$extension,$stats,$prepared){
                $id=Database::insert('INSERT INTO translation_imports(user_id,file_name,file_format,total_rows,new_count,updated_count,unknown_count,error_count,report_json) VALUES(?,?,?,?,?,?,?,?,?)',[(int)$user['id'],mb_substr(basename((string)$file['name']),0,255),$extension,$stats['total'],$stats['new'],$stats['updated'],$stats['unknown'],$stats['errors'],json_encode($stats,JSON_UNESCAPED_UNICODE)]);
                foreach($prepared as $item){$meta=$item['meta']??[];Database::exec('INSERT INTO translation_import_rows(import_id,`row_number`,translation_key,entity_type,entity_id,field_name,source_locale,source_text,source_hash,translations_json,action_name,validation_state,messages_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$item['row_number'],$item['key'],(string)($meta['entity_type']??''),(int)($meta['entity_id']??0),(string)($meta['field']??''),($meta['source_locale']??default_locale()),$item['source_text'],$item['source_hash'],json_encode($item['translations'],JSON_UNESCAPED_UNICODE),$item['action'],$item['state'],json_encode($item['messages'],JSON_UNESCAPED_UNICODE)]);} return $id;
            });
            audit_log('translations_import_preview','translation_import',$importId,(string)$file['name']); redirect('/admin/languages/import/'.$importId);
        }catch(\Throwable $e){flash('error','تعذر تحليل الملف: '.$e->getMessage());redirect('/admin/languages#translation-exchange');}
    }

    public function importPreview(string $id): void {
        $this->admin(); $import=Database::one('SELECT i.*,u.name user_name FROM translation_imports i LEFT JOIN users u ON u.id=i.user_id WHERE i.id=?',[(int)$id]);
        if(!$import){http_response_code(404);exit('سجل الاستيراد غير موجود.');}
        $rows=Database::all('SELECT * FROM translation_import_rows WHERE import_id=? ORDER BY `row_number` LIMIT 2000',[(int)$id]);
        View::render('admin/translation-import',compact('import','rows')+['title'=>'معاينة استيراد الترجمات'],'dashboard');
    }

    public function confirmTranslationImport(string $id): void {
        $user=$this->admin(); Csrf::enforce(); $import=Database::one("SELECT * FROM translation_imports WHERE id=? AND status='preview'",[(int)$id]);
        if(!$import){flash('error','هذا الاستيراد غير متاح للتأكيد.');redirect('/admin/languages/import/'.(int)$id);}
        $rows=Database::all("SELECT * FROM translation_import_rows WHERE import_id=? AND validation_state IN ('valid','warning') ORDER BY `row_number`",[(int)$id]);
        $catalog=TranslationExchange::catalog();
        usort($rows,function($a,$b)use($catalog){$fa=$catalog[$a['translation_key']]['field']??'';$fb=$catalog[$b['translation_key']]['field']??'';$pa=in_array($fa,['title','name','display_name'],true)?0:1;$pb=in_array($fb,['title','name','display_name'],true)?0:1;return $pa<=>$pb ?: ((int)$a['row_number']<=>(int)$b['row_number']);});
        $updated=0;$errors=[];$affectedArticles=[];
        foreach($rows as $row){$meta=$catalog[$row['translation_key']]??null;if(!$meta){$errors[]='صف '.$row['row_number'].': المفتاح لم يعد موجودًا.';continue;}$translations=json_decode((string)$row['translations_json'],true)?:[];
            foreach($translations as $locale=>$value){if(!isset(all_locales()[$locale])||trim((string)$value)==='')continue;try{$missing=array_diff(TranslationExchange::placeholders((string)$meta['source_text']),TranslationExchange::placeholders((string)$value));if($missing)throw new \RuntimeException('Placeholders مفقودة: '.implode(', ',$missing));$state=$row['validation_state']==='warning'?'needs_review':'translated';TranslationExchange::writeTranslation($meta,(string)$locale,(string)$value,(int)$user['id'],$state);if(($meta['entity_type']??'')==='article')$affectedArticles[(int)$meta['entity_id'].':'.$locale]=[(int)$meta['entity_id'],(string)$locale];$updated++;}catch(\Throwable $e){$errors[]='صف '.$row['row_number'].' / '.$locale.': '.$e->getMessage();}}
        }
        $validator=new TranslationCompletenessValidator();
        foreach($affectedArticles as [$articleId,$locale]){try{$validator->invalidate($articleId,$locale);$validator->validateArticle($articleId,$locale,(int)$user['id'],false);}catch(\Throwable $e){$errors[]='فحص المقال '.$articleId.' / '.$locale.': '.$e->getMessage();}}
        $report=['updated'=>$updated,'errors'=>count($errors),'messages'=>array_slice($errors,0,200)];
        Database::exec("UPDATE translation_imports SET status='confirmed',updated_count=?,error_count=?,report_json=?,confirmed_at=CURRENT_TIMESTAMP WHERE id=?",[$updated,count($errors),json_encode($report,JSON_UNESCAPED_UNICODE),(int)$id]);
        audit_log('translations_import_confirm','translation_import',(int)$id,'updated='.$updated.' errors='.count($errors)); flash($errors?'error':'success',$errors?'تم الاستيراد مع '.count($errors).' خطأ. راجع التقرير.':'تم استيراد '.$updated.' ترجمة، وأُرسلت ترجمات المحتوى إلى مسار المراجعة.'); redirect('/admin/languages/import/'.(int)$id);
    }

    public function messages(): void {
        $this->admin();
        $rows=Database::all("SELECT * FROM contact_messages ORDER BY FIELD(status,'new','read','replied','archived'),created_at DESC LIMIT 500");
        View::render('admin/messages',compact('rows')+['title'=>'رسائل التواصل'],'dashboard');
    }
    public function updateMessage(): void {
        $this->admin(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??'read');
        if(!in_array($status,['new','read','replied','archived'],true)) $status='read';
        $note=mb_substr(trim((string)($_POST['admin_note']??'')),0,4000);
        Database::exec('UPDATE contact_messages SET status=?,admin_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$note,$id]);
        audit_log('contact_message_update','contact_message',$id,$status); flash('success','تم تحديث الرسالة.'); redirect('/admin/messages');
    }
    public function deleteMessage(): void {
        $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        Database::exec('DELETE FROM contact_messages WHERE id=?',[$id]); audit_log('contact_message_delete','contact_message',$id,''); redirect('/admin/messages');
    }

    public function storySubmissions(): void {
        $this->admin();
        $rows=Database::all("SELECT s.*,ct.name category_name FROM story_submissions s LEFT JOIN category_translations ct ON ct.category_id=s.category_id AND ct.locale=s.locale ORDER BY FIELD(s.status,'new','reviewed','accepted','rejected'),s.created_at DESC LIMIT 300");
        View::render('admin/story-submissions',compact('rows')+['title'=>'القصص المرسلة'],'dashboard');
    }
    public function updateStorySubmission(): void {
        $this->admin(); Csrf::enforce();
        $id=(int)($_POST['id']??0); $status=(string)($_POST['status']??'reviewed');
        if(!in_array($status,['new','reviewed','accepted','rejected','changes_requested'],true)) $status='reviewed';
        $note=mb_substr(trim((string)($_POST['admin_note']??'')),0,4000);
        Database::exec('UPDATE story_submissions SET status=?,admin_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$note,$id]);
        audit_log('story_submission_update','story_submission',$id,$status); flash('success','تم تحديث القصة المرسلة.'); redirect('/admin/story-submissions');
    }
    public function publishStorySubmission(): void {
        $admin=$this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $story=Database::one('SELECT * FROM story_submissions WHERE id=? LIMIT 1',[$id]);
        if(!$story){flash('error','القصة غير موجودة.');redirect('/admin/story-submissions');}
        if(!in_array((string)$story['status'],['accepted','reviewed'],true)){flash('error','يجب قبول القصة أو مراجعتها قبل نشرها.');redirect('/admin/story-submissions');}
        $authorId=(int)($story['author_user_id']??0); if(!$authorId){flash('error','هذه قصة زائر. اربطها بحساب كاتب قبل النشر حتى يظهر اسم الكاتب بشكل صحيح.');redirect('/admin/story-submissions');}
        $slug=slugify((string)$story['title']).'-'.$id; $exists=Database::one('SELECT id FROM article_translations WHERE locale=? AND slug=? LIMIT 1',['ar',$slug]); if($exists)$slug.='-'.time();
        $articleId=Database::insert('INSERT INTO articles(author_id,category_id,status,is_featured,featured_image,published_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP)',[$authorId,(int)$story['category_id'],'published',0,$story['image']]);
        $content=sanitize_html((string)$story['story']); $excerpt=mb_substr(trim(strip_tags($content)),0,240);
        Database::insert('INSERT INTO article_translations(article_id,locale,title,slug,excerpt,content,status,review_state) VALUES(?,?,?,?,?,?,?,?)',[$articleId,'ar',(string)$story['title'],$slug,$excerpt,$content,'published','approved']);
        Database::exec("UPDATE story_submissions SET status='accepted',admin_note=CONCAT(COALESCE(admin_note,''), ?) ,updated_at=CURRENT_TIMESTAMP WHERE id=?",["\nتم نشر القصة كمقال بتاريخ ".date('Y-m-d H:i:s').".",$id]);
        audit_log('story_submission_publish','story_submission',$id,(string)$articleId); flash('success','تم نشر النسخة العربية. يمكنك الآن إضافة الترجمات من محرر المقال.'); redirect('/writer/articles/'.$articleId);
    }
    public function deleteStorySubmission(): void {
        $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $row=Database::one('SELECT image FROM story_submissions WHERE id=?',[$id]);
        if($row && !empty($row['image'])){
            $image=(string)$row['image'];
            if(\App\Core\ObjectStorage::enabled()) \App\Core\ObjectStorage::delete($image);
            else { $path=BASE_PATH.$image; if(is_file($path) && str_starts_with(realpath($path)?:'',realpath(BASE_PATH.'/uploads')?:'!')) @unlink($path); }
        }
        Database::exec('DELETE FROM story_submissions WHERE id=?',[$id]); audit_log('story_submission_delete','story_submission',$id,''); redirect('/admin/story-submissions');
    }
    public function newsletter(): void {
        $this->admin();
        $rows=Database::all("SELECT * FROM newsletter_subscribers ORDER BY status='active' DESC,created_at DESC LIMIT 500");
        View::render('admin/newsletter',compact('rows')+['title'=>'مشتركو البريد'],'dashboard');
    }
    public function newsletterToggle(): void {
        $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        Database::exec("UPDATE newsletter_subscribers SET status=IF(status='active','unsubscribed','active') WHERE id=?",[$id]);
        redirect('/admin/newsletter');
    }
    public function newsletterDelete(): void {
        $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0); Database::exec('DELETE FROM newsletter_subscribers WHERE id=?',[$id]); redirect('/admin/newsletter');
    }

    public function media(): void {
        $u=$this->content(); $q=trim((string)($_GET['q']??'')); $type=(string)($_GET['type']??''); $sort=(string)($_GET['sort']??'newest');
        $view=(string)($_GET['view']??'library'); $folderId=(int)($_GET['folder']??0);
        if(!in_array($view,['library','trash','recent'],true)) $view='library';
        $args=[]; $parts=[$view==='trash' ? 'm.deleted_at IS NOT NULL' : 'm.deleted_at IS NULL'];
        if($view==='recent') $parts[]='m.updated_at>=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 30 DAY)';
        if($folderId>0 && $view!=='trash'){ $parts[]='m.folder_id=?'; $args[]=$folderId; }
        if($q!==''){ $parts[]='(m.original_name LIKE ? OR m.alt_text LIKE ? OR m.title LIKE ? OR m.caption LIKE ? OR m.description LIKE ?)'; $like='%'.$q.'%'; array_push($args,$like,$like,$like,$like,$like); }
        if($type==='image') $parts[]="m.mime LIKE 'image/%'";
        elseif($type==='document') $parts[]="(m.mime LIKE 'application/%' OR m.mime LIKE 'text/%') AND m.mime NOT LIKE 'application/zip%'";
        elseif($type==='audio') $parts[]="m.mime LIKE 'audio/%'";
        elseif($type==='video') $parts[]="m.mime LIKE 'video/%'";
        elseif($type==='archive') $parts[]="m.mime='application/zip'";
        elseif(in_array($type,['webp','jpeg','png','avif','gif'],true)){ $parts[]='m.mime=?'; $args[]='image/'.$type; }
        $order=match($sort){'oldest'=>'m.id ASC','name'=>'COALESCE(NULLIF(m.title,\'\'),m.original_name) ASC','size'=>'m.size_bytes DESC',default=>'m.id DESC'};
        $where=implode(' AND ',$parts);
        $media=Database::all("SELECT m.*,u.name uploader_name,f.name folder_name,(SELECT COUNT(*) FROM media_usages mu WHERE mu.media_id=m.id) usage_count FROM media m LEFT JOIN users u ON u.id=m.user_id LEFT JOIN media_folders f ON f.id=m.folder_id WHERE {$where} ORDER BY {$order} LIMIT 500",$args);
        $folders=Database::all('SELECT id,parent_id,name FROM media_folders ORDER BY parent_id,name');
        $usedPaths=[]; try{
            foreach(Database::all("SELECT featured_image path FROM articles WHERE featured_image IS NOT NULL AND featured_image<>''") as $r)$usedPaths[(string)$r['path']]=true;
            foreach($media as $m){ $path=(string)$m['path']; if(isset($usedPaths[$path])) continue; $like='%'.$path.'%'; $used=Database::one('SELECT (SELECT COUNT(*) FROM article_translations WHERE content LIKE ?)+(SELECT COUNT(*) FROM page_translations WHERE content LIKE ?)+(SELECT COUNT(*) FROM page_translations WHERE featured_image=?) c',[$like,$like,$path]); if((int)($used['c']??0)>0)$usedPaths[$path]=true; }
        }catch(\Throwable){}
        $mediaUsage=[]; try {
            foreach($media as $m){$mediaUsage[(int)$m['id']]=[];}
            foreach(Database::all("SELECT id,featured_image,title FROM articles WHERE featured_image IS NOT NULL AND featured_image<>''") as $r){foreach($media as $m)if((string)$m['path']===(string)$r['featured_image'])$mediaUsage[(int)$m['id']][]=['label'=>'غلاف مقال: '.(string)$r['title'],'url'=>'/admin/articles/'.(int)$r['id']];}
            foreach(Database::all("SELECT id,title,content FROM article_translations WHERE content IS NOT NULL AND content<>''") as $r){foreach($media as $m)if(str_contains((string)$r['content'],(string)$m['path']))$mediaUsage[(int)$m['id']][]=['label'=>'داخل مقال: '.(string)$r['title'],'url'=>'/admin/articles/'.(int)$r['id']];}
            foreach(Database::all("SELECT id,title,content FROM page_translations WHERE content IS NOT NULL AND content<>''") as $r){foreach($media as $m)if(str_contains((string)$r['content'],(string)$m['path']))$mediaUsage[(int)$m['id']][]=['label'=>'داخل صفحة: '.(string)$r['title'],'url'=>'/admin/pages/'.(int)$r['id']];}
            foreach(Database::all("SELECT id,title,image FROM story_submissions WHERE image IS NOT NULL AND image<>''") as $r){foreach($media as $m)if((string)$m['path']===(string)$r['image'])$mediaUsage[(int)$m['id']][]=['label'=>'صورة قصة: '.(string)$r['title'],'url'=>'/admin/story-submissions'];}
        } catch(\Throwable) {}
        View::render('admin/media',compact('media','q','type','sort','view','folderId','folders','usedPaths','mediaUsage')+['title'=>'مكتبة الوسائط'],'dashboard');
    }

    private function canManageMedia(array $media,array $user): bool {
        return (string)($user['role']??'')==='admin' || (int)($media['user_id']??0)===(int)($user['id']??0);
    }

    private function normalizedMediaFiles(): array {
        $bag=$_FILES['files']??($_FILES['images']??null);
        if(!$bag || !isset($bag['name'])) return isset($_FILES['image'])?[$_FILES['image']]:[];
        if(!is_array($bag['name'])) return [$bag];
        $out=[]; foreach(array_keys($bag['name']) as $i) $out[]=['name'=>$bag['name'][$i]??'','type'=>$bag['type'][$i]??'','tmp_name'=>$bag['tmp_name'][$i]??'','error'=>$bag['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$bag['size'][$i]??0];
        return $out;
    }

    public function uploadMedia(): void {
        $u=$this->content(); Csrf::enforce(); $files=$this->normalizedMediaFiles();
        if(!$files){ flash('error','اختر ملفًا واحدًا على الأقل من جهازك.'); redirect('/admin/media'); }
        $ok=0; $errors=[];
        foreach(array_slice($files,0,30) as $file){
            try{
                $info=Upload::fileDetailed($file); if(!$info) continue;
                $title=count($files)===1?trim((string)($_POST['title']??'')):pathinfo((string)$info['original_name'],PATHINFO_FILENAME);
                if($title==='')$title=pathinfo((string)$info['original_name'],PATHINFO_FILENAME);
                $alt=count($files)===1?trim((string)($_POST['alt_text']??'')):'';
                $folderId=(int)($_POST['folder_id']??0)?:null; $diskPath=BASE_PATH.(string)$info['path']; $hash=is_file($diskPath)?hash_file('sha256',$diskPath):null;
                $id=Database::insert('INSERT INTO media(user_id,folder_id,path,storage_key,mime,kind,alt_text,title,caption,description,original_name,width,height,size_bytes,file_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[(int)$u['id'],$folderId,$info['path'],$info['path'],$info['mime'],$info['kind']??null,$alt,$title,'','',$info['original_name'],$info['width'],$info['height'],$info['size_bytes'],$hash]);
                audit_log('media_upload','media',$id,$info['path']); $ok++;
            }catch(\Throwable $e){ $errors[]=$e->getMessage(); }
        }
        if($ok) flash('success','تم رفع '.$ok.' ملف/ملفات إلى مكتبة الوسائط.');
        if($errors) flash('error',implode(' · ',array_slice(array_unique($errors),0,3)));
        redirect('/admin/media');
    }

    public function uploadMediaAjax(): void {
        $u=$this->content(); Csrf::enforce(); header('Content-Type: application/json; charset=utf-8');
        try{
            $file=$_FILES['file']??[]; $info=Upload::imageDetailed($file,true); if(!$info) throw new \RuntimeException('اختر صورة أولًا.');
            $title=trim((string)($_POST['title']??pathinfo((string)$info['original_name'],PATHINFO_FILENAME))); $alt=trim((string)($_POST['alt_text']??''));
            $diskPath=BASE_PATH.(string)$info['path']; $hash=is_file($diskPath)?hash_file('sha256',$diskPath):null;
            $id=Database::insert('INSERT INTO media(user_id,path,storage_key,mime,kind,alt_text,title,caption,description,original_name,width,height,size_bytes,file_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[(int)$u['id'],$info['path'],$info['path'],$info['mime'],$info['kind']??'image',$alt,$title,'','',$info['original_name'],$info['width'],$info['height'],$info['size_bytes'],$hash]);
            audit_log('media_upload','media',$id,$info['path']); echo json_encode(['ok'=>true,'id'=>$id,'path'=>$info['path'],'mime'=>$info['mime'],'title'=>$title,'alt'=>$alt,'width'=>$info['width'],'height'=>$info['height']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }catch(\Throwable $e){ http_response_code(422); echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE); }
    }

    public function updateMedia(): void {
        $u=$this->content(); Csrf::enforce(); $id=(int)($_POST['id']??0); $media=Database::one('SELECT * FROM media WHERE id=? AND deleted_at IS NULL',[$id]);
        if(!$media || !$this->canManageMedia($media,$u)){ http_response_code(403); flash('error','لا تملك صلاحية تعديل هذا الملف.'); redirect('/admin/media'); }
        Database::exec('UPDATE media SET title=?,alt_text=?,caption=?,description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[mb_substr(trim((string)($_POST['title']??'')),0,190),mb_substr(trim((string)($_POST['alt_text']??'')),0,255),mb_substr(trim((string)($_POST['caption']??'')),0,500),mb_substr(trim((string)($_POST['description']??'')),0,30000),$id]); audit_log('media_update','media',$id,''); flash('success','تم تحديث بيانات الملف.'); redirect('/admin/media');
    }
    public function deleteMedia(): void {
        $u=$this->content(); Csrf::enforce(); $id=(int)($_POST['id']??0); $m=Database::one('SELECT * FROM media WHERE id=? AND deleted_at IS NULL',[$id]);
        if($m){$path=(string)$m['path']; $used=Database::one("SELECT (SELECT COUNT(*) FROM articles WHERE featured_image=?) + (SELECT COUNT(*) FROM article_translations WHERE content LIKE ?) + (SELECT COUNT(*) FROM page_translations WHERE content LIKE ?) + (SELECT COUNT(*) FROM story_submissions WHERE image=?) c",[$path,'%'.$path.'%','%'.$path.'%',$path]); if((int)($used['c']??0)>0){flash('error','لا يمكن نقل ملف مستخدم إلى السلة قبل إزالة مراجعته من المحتوى.');redirect('/admin/media');}}
        if($m && $this->canManageMedia($m,$u)){ Database::exec('UPDATE media SET deleted_at=CURRENT_TIMESTAMP,deleted_by=? WHERE id=?',[(int)$u['id'],$id]); audit_log('media_trash','media',$id,(string)$m['path']); }
        redirect('/admin/media');
    }

    public function bulkDeleteMedia(): void {
        $u=$this->content(); Csrf::enforce(); $ids=is_array($_POST['ids']??null)?array_values(array_filter(array_map('intval',$_POST['ids']))):[]; $deleted=0;
        foreach(array_slice($ids,0,100) as $id){ $m=Database::one('SELECT * FROM media WHERE id=? AND deleted_at IS NULL',[$id]); if(!$m || !$this->canManageMedia($m,$u))continue; Database::exec('UPDATE media SET deleted_at=CURRENT_TIMESTAMP,deleted_by=? WHERE id=?',[(int)$u['id'],$id]); $deleted++; }
        audit_log('media_bulk_delete','media',null,(string)$deleted); flash('success','تم حذف '.$deleted.' ملف/ملفات.'); redirect('/admin/media');
    }

    public function restoreMedia(): void { $u=$this->content(); Csrf::enforce(); $id=(int)($_POST['id']??0); $m=Database::one('SELECT * FROM media WHERE id=? AND deleted_at IS NOT NULL',[$id]); if($m && $this->canManageMedia($m,$u)){Database::exec('UPDATE media SET deleted_at=NULL,deleted_by=NULL WHERE id=?',[$id]);audit_log('media_restore','media',$id,'');} redirect('/admin/media?view=trash'); }
    public function createMediaFolder(): void { $u=$this->content(); Csrf::enforce(); $name=mb_substr(trim((string)($_POST['name']??'')),0,160); $parent=(int)($_POST['parent_id']??0)?:null; if($name===''){flash('error','اكتب اسم المجلد.');redirect('/admin/media');} try{Database::insert('INSERT INTO media_folders(parent_id,name,created_by) VALUES(?,?,?)',[$parent,$name,(int)$u['id']]);audit_log('media_folder_create','media_folder',null,$name);flash('success','تم إنشاء المجلد.');}catch(\Throwable){flash('error','اسم المجلد مستخدم ضمن هذا الموقع.');} redirect('/admin/media'); }

    public function blocks(): void {
        $this->admin();
        $blocks=Database::all('SELECT b.*,a.label ad_label FROM site_blocks b LEFT JOIN ads a ON a.id=b.ad_id ORDER BY b.slot,b.sort_order,b.id');
        $ads=Database::all('SELECT id,label,slot FROM ads ORDER BY id DESC');
        $media=Database::all('SELECT id,path,title,original_name FROM media ORDER BY id DESC LIMIT 200');
        View::render('admin/blocks',compact('blocks','ads','media')+['title'=>'أقسام الصور والإعلانات'],'dashboard');
    }
    public function saveBlock(): void {
        $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0);
        $type=in_array($_POST['type']??'image',['image','ad'],true)?$_POST['type']:'image';
        $slot=in_array($_POST['slot']??'home_after_featured',['home_before_featured','home_after_featured','home_before_latest','home_after_latest','home_before_categories','home_after_categories','article_after_cover','article_before_related','footer_before'],true)?$_POST['slot']:'home_after_featured';
        $imageUrl=trim((string)($_POST['image_url_custom']??'')); if($imageUrl==='') $imageUrl=trim((string)($_POST['image_url']??''));
        $data=[$type,$slot,(string)($_POST['locale']??'*'),trim((string)($_POST['title']??'')),$imageUrl,trim((string)($_POST['target_url']??'')),(int)($_POST['ad_id']??0)?:null,(int)($_POST['sort_order']??0),isset($_POST['active'])?1:0];
        if($id) Database::exec('UPDATE site_blocks SET type=?,slot=?,locale=?,title=?,image_url=?,target_url=?,ad_id=?,sort_order=?,active=? WHERE id=?',[...$data,$id]);
        else $id=Database::insert('INSERT INTO site_blocks(type,slot,locale,title,image_url,target_url,ad_id,sort_order,active) VALUES(?,?,?,?,?,?,?,?,?)',$data);
        audit_log('site_block_save','site_block',$id,$slot); flash('success','تم حفظ القسم ومكان ظهوره.'); redirect('/admin/blocks');
    }
    public function deleteBlock(): void { $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0); Database::exec('DELETE FROM site_blocks WHERE id=?',[$id]); audit_log('site_block_delete','site_block',$id,''); redirect('/admin/blocks'); }

    public function restoreBackup(): void {
        $this->admin(); Csrf::enforce();
        if(empty($_FILES['backup']['tmp_name'])){ flash('error','اختر ملف SQL.'); redirect('/admin/backup'); }
        if(($_FILES['backup']['size']??0)>25*1024*1024){ flash('error','ملف النسخة أكبر من 25MB.'); redirect('/admin/backup'); }
        $sql=(string)file_get_contents($_FILES['backup']['tmp_name']);
        if($sql==='' || stripos($sql,'CREATE TABLE')===false){ flash('error','الملف لا يبدو نسخة SQL صالحة.'); redirect('/admin/backup'); }
        try {
            $snapshot=DatabaseBackup::saveSnapshot('pre-restore');
        } catch(\Throwable $e) {
            flash('error','تعذر إنشاء نقطة استعادة آمنة قبل التنفيذ. لم يتم تغيير أي بيانات.');
            redirect('/admin/backup');
        }
        $pdo=Database::pdo();
        try{
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $statements=$this->splitSql($sql); foreach($statements as $stmt){ if(trim($stmt)!=='') $pdo->exec($stmt); }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); audit_log('backup_restore','database',null,'sql restore; rollback snapshot: '.basename($snapshot)); flash('success','تم استرجاع النسخة الاحتياطية. سجّل الدخول مجددًا إذا تغيرت بيانات الحسابات.');
        }catch(\Throwable $e){ try{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}catch(\Throwable){} flash('error','فشل الاسترجاع: '.$e->getMessage()); }
        redirect('/admin/backup');
    }
    private function splitSql(string $sql): array {
        $out=[];$buf='';$quote=null;$len=strlen($sql);
        for($i=0;$i<$len;$i++){ $c=$sql[$i]; $n=$i+1<$len?$sql[$i+1]:''; if($quote){$buf.=$c;if($c==='\\'&&$n!==''){$buf.=$n;$i++;continue;}if($c===$quote)$quote=null;continue;} if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;} if($c===';' ){ $out[]=$buf;$buf='';continue;} $buf.=$c; }
        if(trim($buf)!=='')$out[]=$buf; return $out;
    }

    public function system(): void {
        $this->admin();
        $checks=[]; $checks[]=['PHP',PHP_VERSION,version_compare(PHP_VERSION,'8.1','>=')];
        $checks[]=['PDO MySQL',extension_loaded('pdo_mysql')?'متاح':'غير متاح',extension_loaded('pdo_mysql')];
        $checks[]=['GD/WebP',function_exists('imagewebp')?'متاح':'غير متاح',function_exists('imagewebp')];
        $checks[]=['uploads قابل للكتابة',is_writable(BASE_PATH.'/uploads')?'نعم':'لا',is_writable(BASE_PATH.'/uploads')];
        $checks[]=['storage قابل للكتابة',is_writable(BASE_PATH.'/storage')?'نعم':'لا',is_writable(BASE_PATH.'/storage')];
        try{ Database::one('SELECT 1 ok'); $db=true;}catch(\Throwable){$db=false;} $checks[]=['قاعدة البيانات',$db?'متصلة':'خطأ',$db];
        $disk=@disk_free_space(BASE_PATH); $checks[]=['المساحة الحرة',$disk!==false?round($disk/1073741824,2).' GB':'غير متاحة',$disk===false?false:$disk>500*1024*1024];
        $mailTo=setting('contact_email',''); $checks[]=['بريد التنبيهات',$mailTo?:'غير محدد',filter_var($mailTo,FILTER_VALIDATE_EMAIL)!==false];
        $cronFile=BASE_PATH.'/cron/publish_scheduled.php'; $checks[]=['Cron النشر المجدول',is_file($cronFile)?'ملف المهمة موجود':'ملف المهمة غير موجود',is_file($cronFile)];
        $checks[]=['المنطقة الزمنية',date_default_timezone_get(),date_default_timezone_get()!=='UTC'||(string)config('timezone','')==='UTC'];
        View::render('admin/system',compact('checks')+['title'=>'حالة النظام'],'dashboard');
    }

    public function security(): void {
        $u=$this->admin(); $row=Database::one('SELECT two_factor_enabled,two_factor_secret,two_factor_recovery_codes FROM users WHERE id=?',[$u['id']]);
        if(empty($row['two_factor_secret'])){ $secret=generate_base32_secret(); $_SESSION['pending_2fa_secret']=$secret; } else $secret=(string)$row['two_factor_secret'];
        $securityKeys=['security_login_throttle','security_form_rate_limit','security_spam_honeypot','security_audit_login','security_community_antispam','security_content_protection','security_upload_validation','protect_copy','protect_context','protect_select','protect_drag','protect_save_shortcut','protect_print','protect_image_overlay','protect_watermark_text']; $security=[]; foreach($securityKeys as $k)$security[$k]=setting($k,'');
        View::render('admin/security',compact('row','secret','security')+['title'=>'الحماية والأمان'],'dashboard');
    }
    public function enableTwoFactor(): void {
        $u=$this->admin(); Csrf::enforce(); $secret=(string)($_SESSION['pending_2fa_secret']??''); $code=trim((string)($_POST['code']??''));
        if($secret===''||!totp_verify($secret,$code)){ flash('error','رمز التطبيق غير صحيح.'); redirect('/admin/security'); }
        $plain=[];$hashed=[]; for($i=0;$i<8;$i++){ $c=strtoupper(bin2hex(random_bytes(4))); $plain[]=$c; $hashed[]=hash('sha256',$c); }
        Database::exec('UPDATE users SET two_factor_enabled=1,two_factor_secret=?,two_factor_recovery_codes=? WHERE id=?',[$secret,json_encode($hashed),$u['id']]); unset($_SESSION['pending_2fa_secret']); $_SESSION['two_factor_recovery_plain']=$plain; audit_log('two_factor_enable','user',(int)$u['id'],''); flash('success','تم تفعيل التحقق بخطوتين. احفظ رموز الاسترداد المعروضة.'); redirect('/admin/security');
    }
    public function disableTwoFactor(): void { $u=$this->admin(); Csrf::enforce(); Database::exec('UPDATE users SET two_factor_enabled=0,two_factor_secret=NULL,two_factor_recovery_codes=NULL WHERE id=?',[$u['id']]); audit_log('two_factor_disable','user',(int)$u['id'],''); flash('success','تم تعطيل التحقق بخطوتين.'); redirect('/admin/security'); }

    public function saveSecurity(): void {
        $this->admin(); Csrf::enforce();
        $toggle=['security_login_throttle','security_form_rate_limit','security_spam_honeypot','security_audit_login','security_community_antispam','security_content_protection','security_upload_validation','protect_copy','protect_context','protect_select','protect_drag','protect_save_shortcut','protect_print','protect_image_overlay'];
        foreach($toggle as $k) $this->putSetting($k,isset($_POST[$k])?'1':'0');
        $this->putSetting('protect_watermark_text',mb_substr(trim((string)($_POST['protect_watermark_text']??'')),0,80));
        audit_log('security_settings_save','settings',null,'security center'); flash('success','تم حفظ إعدادات الحماية والأمان.'); redirect('/admin/security');
    }

    public function links(): void {
        $this->admin();
        $redirects=Database::all('SELECT * FROM redirects ORDER BY id DESC LIMIT 300');
        $notFound=[]; try{$notFound=Database::all('SELECT * FROM not_found_logs ORDER BY hits DESC,last_seen_at DESC LIMIT 300');}catch(\Throwable){}
        $integrity=[];
        try{
            foreach(Database::all("SELECT a.id FROM articles a WHERE a.status='published' AND NOT EXISTS(SELECT 1 FROM article_translations t WHERE t.article_id=a.id AND ".article_translation_publishable_sql('t').") LIMIT 100") as $r) $integrity[]=['type'=>'مقال','issue'=>'مقال منشور بلا ترجمة مكتملة ومعتمدة أو رابط صالح','admin'=>'/admin/articles/'.(int)$r['id']];
            foreach(Database::all("SELECT p.id FROM pages p WHERE p.status='published' AND NOT EXISTS(SELECT 1 FROM page_translations t WHERE t.page_id=p.id AND TRIM(t.slug)<>'') LIMIT 100") as $r) $integrity[]=['type'=>'صفحة','issue'=>'صفحة منشورة بلا Slug صالح','admin'=>'/admin/pages/'.(int)$r['id']];
            foreach(Database::all("SELECT article_id id,locale FROM article_translations WHERE TRIM(slug)='' OR TRIM(title)='' LIMIT 100") as $r) $integrity[]=['type'=>'مقال '.strtoupper((string)$r['locale']),'issue'=>'عنوان أو Slug فارغ','admin'=>'/admin/articles/'.(int)$r['id']];
            // Title-only shells are useful while translation work is in progress,
            // but they must never be mistaken for a public translation.
            foreach(Database::all("SELECT article_id id,locale FROM article_translations WHERE TRIM(COALESCE(title,''))<>'' AND TRIM(COALESCE(slug,''))<>'' AND TRIM(COALESCE(content,''))='' LIMIT 100") as $r) $integrity[]=['type'=>'مقال '.strtoupper((string)$r['locale']),'issue'=>'ترجمة عنوان/رابط فقط؛ أضف المحتوى ثم انشر واعتمد النسخة','admin'=>'/admin/articles/'.(int)$r['id']];
            foreach(Database::all("SELECT page_id id,locale FROM page_translations WHERE TRIM(slug)='' OR TRIM(title)='' LIMIT 100") as $r) $integrity[]=['type'=>'صفحة '.strtoupper((string)$r['locale']),'issue'=>'عنوان أو Slug فارغ','admin'=>'/admin/pages/'.(int)$r['id']];
            foreach(Database::all("SELECT ct.category_id id,ct.locale FROM category_translations ct LEFT JOIN languages l ON l.code=ct.locale WHERE TRIM(ct.slug)='' OR TRIM(ct.name)='' OR l.id IS NULL LIMIT 100") as $r) $integrity[]=['type'=>'تصنيف '.strtoupper((string)$r['locale']),'issue'=>'ترجمة تصنيف غير مكتملة أو لغة غير مسجلة','admin'=>'/admin/categories'];
        }catch(\Throwable){}
        $stats=['redirects'=>count($redirects),'404'=>count($notFound),'404_hits'=>array_sum(array_map(fn($r)=>(int)($r['hits']??0),$notFound)),'integrity'=>count($integrity)];
        View::render('admin/links',compact('redirects','notFound','stats','integrity')+['title'=>'الروابط و404'],'dashboard');
    }
    public function saveRedirect(): void {
        $this->admin(); Csrf::enforce(); $source=trim((string)($_POST['source_path']??'')); $target=trim((string)($_POST['target_url']??'')); $code=(int)($_POST['http_code']??301); if(!in_array($code,[301,302,307,308],true))$code=301;
        if($source===''||$source[0]!=='/'||str_starts_with($source,'//')||$target===''||(!str_starts_with($target,'/')&&!filter_var($target,FILTER_VALIDATE_URL))){ flash('error','تحقق من الرابط القديم والجديد.'); redirect('/admin/links'); }
        $source=parse_url($source,PHP_URL_PATH)?:$source; Database::exec('INSERT INTO redirects(source_path,target_url,http_code) VALUES(?,?,?) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),http_code=VALUES(http_code)',[$source,$target,$code]);
        audit_log('redirect_save','redirect',null,$source.' -> '.$target); flash('success','تم حفظ التحويل.'); redirect('/admin/links');
    }
    public function deleteRedirect(): void { $this->admin(); Csrf::enforce(); $id=(int)($_POST['id']??0); Database::exec('DELETE FROM redirects WHERE id=?',[$id]); audit_log('redirect_delete','redirect',$id,''); redirect('/admin/links'); }
    public function clear404Logs(): void { $this->admin(); Csrf::enforce(); Database::exec('DELETE FROM not_found_logs'); audit_log('404_logs_clear','not_found_logs',null,''); flash('success','تم مسح سجل 404.'); redirect('/admin/links'); }

    public function revisions(string $id): void {
        $u=$this->content(); $article=Database::one('SELECT * FROM articles WHERE id=?',[(int)$id]); if(!$article){http_response_code(404);exit('غير موجود');}
        $rows=Database::all('SELECT r.*,u.name editor_name FROM article_revisions r JOIN users u ON u.id=r.user_id WHERE r.article_id=? ORDER BY r.id DESC LIMIT 100',[(int)$id]);
        $currentRows=Database::all('SELECT locale,title,content FROM article_translations WHERE article_id=?',[(int)$id]); $current=[]; foreach($currentRows as $cr)$current[$cr['locale']]=$cr;
        View::render('admin/revisions',compact('article','rows','current')+['title'=>'نسخ المقال السابقة'],'dashboard');
    }
    public function restoreRevision(): void {
        $this->content(); Csrf::enforce(); $rid=(int)($_POST['revision_id']??0); $r=Database::one('SELECT * FROM article_revisions WHERE id=?',[$rid]); if(!$r){flash('error','النسخة غير موجودة.');redirect('/admin/articles');}
        Database::transaction(function()use($r){
            $current=Database::one('SELECT * FROM article_translations WHERE article_id=? AND locale=?',[$r['article_id'],$r['locale']]);
            if($current) Database::exec('UPDATE article_translations SET title=?,excerpt=?,content=?,seo_title=?,seo_description=?,seo_keywords=?,hashtags=?,updated_at=CURRENT_TIMESTAMP WHERE article_id=? AND locale=?',[$r['title'],$r['excerpt'],$r['content'],$r['seo_title'],$r['seo_description'],$r['seo_keywords'],$r['hashtags'],$r['article_id'],$r['locale']]);
            Database::exec('UPDATE articles SET category_id=COALESCE(?,category_id),featured_image=COALESCE(NULLIF(?,\'\'),featured_image),updated_at=CURRENT_TIMESTAMP WHERE id=?',[$r['category_id'],$r['featured_image'],$r['article_id']]);
        });
        audit_log('revision_restore','article',(int)$r['article_id'],'revision '.$rid); flash('success','تم استعادة النسخة المحددة.'); redirect('/writer/articles/'.(int)$r['article_id']);
    }

    private function putSetting(string $key,string $value): void { $x=Database::one('SELECT `key` FROM settings WHERE `key`=?',[$key]); if($x)Database::exec('UPDATE settings SET value=? WHERE `key`=?',[$value,$key]); else Database::insert('INSERT INTO settings(`key`,value) VALUES(?,?)',[$key,$value]); }
}
