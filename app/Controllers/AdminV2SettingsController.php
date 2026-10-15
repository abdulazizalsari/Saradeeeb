<?php
namespace App\Controllers;

use App\Core\Database;

final class AdminV2SettingsController extends AdminV2BaseController
{
    public function pages(): void
    {
        $u=$this->admin();
        $pages=Database::all("SELECT p.id,p.status,p.sort_order,p.template,p.deleted_at,p.updated_at,COALESCE(pt.title,'') title,COALESCE(pt.slug,'') slug,COALESCE(pt.locale,'ar') locale FROM pages p LEFT JOIN page_translations pt ON pt.page_id=p.id AND pt.locale='ar' ORDER BY p.deleted_at IS NOT NULL,p.sort_order,p.id DESC");
        $editId=max(0,(int)($_GET['edit']??0));
        $formPage=$editId?Database::one("SELECT p.id,p.status,pt.title,pt.slug,pt.content,pt.locale FROM pages p LEFT JOIN page_translations pt ON pt.page_id=p.id AND pt.locale='ar' WHERE p.id=? AND p.deleted_at IS NULL",[$editId]):null;
        $this->render('pages-v2',compact('pages','u','formPage','editId'),'إدارة الصفحات');
    }

    public function savePage(): void
    {
        $u=$this->admin(); $this->csrf(); $id=(int)($_POST['id']??0); $locale=(string)($_POST['locale']??'ar');
        if(!in_array($locale,array_keys(all_locales()),true))$locale='ar';
        $title=trim((string)($_POST['title']??'')); $slug=trim((string)($_POST['slug']??'')); $content=(string)($_POST['content']??''); $status=($_POST['status']??'draft')==='published'?'published':'draft';
        if($title===''||$slug==='')$this->redirect('/admin-v2/pages','العنوان وSlug مطلوبان.','error');
        $slug=slugify($slug); if($slug==='')$this->redirect('/admin-v2/pages','Slug غير صالح.','error');
        try { Database::transaction(function()use($id,$locale,$title,$slug,$content,$status,$u){
            if($id){$page=Database::one('SELECT id FROM pages WHERE id=? AND deleted_at IS NULL',[$id]);if(!$page)throw new \RuntimeException('missing');Database::exec('UPDATE pages SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$status,$id]);Database::exec('INSERT INTO page_translations(page_id,locale,title,slug,content,status,review_state) VALUES(?,?,?,?,?, ?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),slug=VALUES(slug),content=VALUES(content),status=VALUES(status),review_state=VALUES(review_state)',[$id,$locale,$title,$slug,$content,$status,$status==='published'?'approved':'draft']);
            } else {$id=Database::insert('INSERT INTO pages(status) VALUES(?)',[$status]);Database::insert('INSERT INTO page_translations(page_id,locale,title,slug,content,status,review_state) VALUES(?,?,?,?,?,?,?)',[$id,$locale,$title,$slug,$content,$status,$status==='published'?'approved':'draft']);}
        }); } catch(\Throwable){$this->redirect('/admin-v2/pages','تعذر حفظ الصفحة أو أن Slug مستخدم.','error');}
        audit_log('admin_v2_page_saved','page',$id,$status); $this->redirect('/admin-v2/pages','تم حفظ الصفحة.');
    }

    public function pageStatus(): void
    {
        $u=$this->admin(); $this->csrf(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??''); $page=Database::one('SELECT id,status,deleted_at FROM pages WHERE id=?',[$id]); if(!$page)$this->redirect('/admin-v2/pages','الصفحة غير موجودة.','error');
        if($action==='trash')Database::exec('UPDATE pages SET deleted_at=UTC_TIMESTAMP(),status=\'draft\' WHERE id=?',[$id]); elseif($action==='restore')Database::exec('UPDATE pages SET deleted_at=NULL WHERE id=?',[$id]); elseif($action==='toggle')Database::exec("UPDATE pages SET status=IF(status='published','draft','published') WHERE id=? AND deleted_at IS NULL",[$id]); else $this->redirect('/admin-v2/pages','إجراء غير صالح.','error');
        audit_log('admin_v2_page_'.$action,'page',$id,''); $this->redirect('/admin-v2/pages','تم تحديث الصفحة.');
    }

    public function whatsapp(): void
    {
        $u=$this->admin(); $keys=['wa_enabled','wa_brand_name','wa_show_logo','wa_logo_width','wa_button_desktop','wa_button_mobile','wa_modal_width','wa_modal_radius','wa_bottom_desktop','wa_side_desktop','wa_bottom_mobile','wa_side_mobile','wa_show_desktop','wa_show_mobile','wa_float_animation','wa_ripple_animation','wa_open_new_tab','wa_include_context','wa_primary_color','wa_secondary_color','wa_icon_color','wa_ripple_color','wa_modal_bg','wa_modal_text','wa_muted_text','wa_logo_bg'];
        foreach(range(1,4) as $i){$keys[]='wa_contact_'.$i.'_enabled';$keys[]='wa_contact_'.$i.'_name';$keys[]='wa_contact_'.$i.'_phone';foreach(array_keys(whatsapp_default_texts()) as $lang)$keys[]='wa_contact_'.$i.'_role_'.$lang;}
        foreach(array_keys(whatsapp_default_texts()) as $lang)foreach(array_keys(whatsapp_default_texts()[$lang]) as $key)$keys[]='wa_text_'.$lang.'_'.$key;
        $map=[];foreach($keys as $key)$map[$key]=setting($key,''); $this->render('whatsapp-v2',compact('map','u'),'إعدادات واتساب');
    }

    public function saveWhatsapp(): void
    {
        $u=$this->admin(); $this->csrf(); $keys=['wa_enabled','wa_brand_name','wa_show_logo','wa_logo_width','wa_button_desktop','wa_button_mobile','wa_modal_width','wa_modal_radius','wa_bottom_desktop','wa_side_desktop','wa_bottom_mobile','wa_side_mobile','wa_show_desktop','wa_show_mobile','wa_float_animation','wa_ripple_animation','wa_open_new_tab','wa_include_context','wa_primary_color','wa_secondary_color','wa_icon_color','wa_ripple_color','wa_modal_bg','wa_modal_text','wa_muted_text','wa_logo_bg'];
        foreach(range(1,4) as $i){$keys[]='wa_contact_'.$i.'_enabled';$keys[]='wa_contact_'.$i.'_name';$keys[]='wa_contact_'.$i.'_phone';foreach(array_keys(whatsapp_default_texts()) as $lang)$keys[]='wa_contact_'.$i.'_role_'.$lang;}
        foreach(array_keys(whatsapp_default_texts()) as $lang)foreach(array_keys(whatsapp_default_texts()[$lang]) as $key)$keys[]='wa_text_'.$lang.'_'.$key;
        foreach($keys as $key){$value=isset($_POST[$key])?(is_array($_POST[$key])?'':trim((string)$_POST[$key])):((str_ends_with($key,'_enabled')||in_array($key,['wa_show_logo','wa_show_desktop','wa_show_mobile','wa_float_animation','wa_ripple_animation','wa_open_new_tab','wa_include_context'],true))?'0':setting($key,''));if(preg_match('/^wa_.*_color$/',$key)&&!preg_match('/^#[0-9a-f]{6}$/i',$value))continue;Database::exec('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]);}
        audit_log('admin_v2_whatsapp_saved','settings',null,''); $this->redirect('/admin-v2/whatsapp','تم حفظ إعدادات واتساب.');
    }
}
