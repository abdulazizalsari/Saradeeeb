<?php
namespace App\Controllers;

use App\Core\Database;

final class AdminV2AccountsController extends AdminV2BaseController
{
    public function applications(): void
    {
        $u=$this->admin(); $status=(string)($_GET['status']??''); $q=trim((string)($_GET['q']??''));
        $where=[]; $params=[];
        if(in_array($status,['pending','under_review','changes_requested','approved','rejected','suspended'],true)){ $where[]='a.status=?'; $params[]=$status; }
        if($q!==''){ $where[]='(a.name LIKE ? OR a.email LIKE ? OR a.username LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%','%'.$q.'%'); }
        $rows=[]; try { $rows=Database::all('SELECT a.*,u.role user_role,u.status user_status,u.last_login_at FROM writer_applications a LEFT JOIN users u ON u.id=a.user_id '.($where?'WHERE '.implode(' AND ',$where):'').' ORDER BY FIELD(a.status,\'pending\',\'under_review\',\'changes_requested\',\'approved\',\'rejected\',\'suspended\'),a.id DESC LIMIT 200',$params); } catch(\Throwable) {}
        $this->render('applications',compact('rows','status','q','u'),'طلبات انتساب الكتّاب');
    }

    public function applicationAction(): void
    {
        $u=$this->admin(); $this->csrf(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'');
        $app=Database::one('SELECT * FROM writer_applications WHERE id=? LIMIT 1',[$id]); if(!$app)$this->redirect('/admin-v2/applications','الطلب غير موجود.','error');
        $map=['review'=>'under_review','request_changes'=>'changes_requested','approve'=>'approved','reject'=>'rejected','suspend'=>'suspended','reopen'=>'pending'];
        if(isset($map[$action])){
            $new=$map[$action]; $note=trim((string)($_POST['reason']??''));
            Database::transaction(function()use($app,$new,$note,$u){
                Database::exec('UPDATE writer_applications SET status=?,admin_note=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$new,$note!==''?$note:null,(int)$app['id']]);
                if(!empty($app['user_id'])){
                    if($new==='approved') Database::exec("UPDATE users SET role='writer',status='active',writer_verified=1,account_trashed_at=NULL,account_trash_reason=NULL WHERE id=?",[(int)$app['user_id']]);
                    elseif(in_array($new,['rejected','suspended'],true)) Database::exec("UPDATE users SET role=IF(role IN ('writer','trusted_writer'),'member',role),status=IF(status='blocked','blocked','pending'),writer_verified=0 WHERE id=?",[(int)$app['user_id']]);
                }
                try { Database::insert('INSERT INTO writer_application_history(application_id,from_status,to_status,admin_id,note) VALUES(?,?,?,?,?)',[(int)$app['id'],$app['status'],$new,(int)$u['id'],$note]); } catch(\Throwable) {}
            });
            audit_log('writer_application_'.$new,'writer_application',$id,$note);
            $this->redirect('/admin-v2/applications','تم تحديث حالة الطلب.');
        }
        if($action==='message'){
            $body=trim((string)($_POST['message']??''));
            if($body==='')$this->redirect('/admin-v2/applications','اكتب الرسالة أولًا.','error');
            try { Database::insert('INSERT INTO writer_application_messages(application_id,sender_id,visibility,body) VALUES(?,?,\'applicant\',?)',[$id,(int)$u['id'],$body]); audit_log('writer_application_message','writer_application',$id,''); } catch(\Throwable) {}
            $this->redirect('/admin-v2/applications','تمت إضافة الرسالة داخل الحساب.');
        }
        $this->redirect('/admin-v2/applications','الإجراء غير متاح.','error');
    }

    public function writers(): void
    {
        $u=$this->admin(); $q=trim((string)($_GET['q']??'')); $status=(string)($_GET['status']??''); $where=["u.role IN ('writer','trusted_writer')"]; $params=[];
        if(in_array($status,['active','blocked','pending'],true)){ $where[]='u.status=?'; $params[]=$status; }
        if($q!==''){ $where[]='(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)'; array_push($params,'%'.$q.'%','%'.$q.'%','%'.$q.'%'); }
        $rows=[]; try { $rows=Database::all('SELECT u.id,u.name,u.username,u.email,u.role,u.status,u.writer_verified,u.writer_directory_hidden,u.writer_suspended_until,u.writer_suspension_reason,u.login_banned_until,u.login_ban_reason,u.account_trashed_at,u.created_at,u.last_login_at FROM users u WHERE '.implode(' AND ',$where).' ORDER BY u.created_at DESC LIMIT 250',$params); } catch(\Throwable) {}
        $this->render('writers',compact('rows','q','status','u'),'حسابات الكتّاب');
    }

    public function writerAction(): void
    {
        $u=$this->admin(); $this->csrf(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'');
        $writer=Database::one("SELECT * FROM users WHERE id=? AND role IN ('writer','trusted_writer') LIMIT 1",[$id]); if(!$writer)$this->redirect('/admin-v2/writers','حساب الكاتب غير موجود.','error');
        $reason=trim((string)($_POST['reason']??'')); $duration=null;
        if(in_array($action,['suspend','ban'],true)){ $duration=$this->duration(); if(!$duration)$this->redirect('/admin-v2/writers','أدخل مدة صحيحة.','error'); }
        $previous=['status'=>$writer['status']??null,'writer_suspended_until'=>$writer['writer_suspended_until']??null,'login_banned_until'=>$writer['login_banned_until']??null,'hidden'=>$writer['writer_directory_hidden']??null];
        switch($action){
            case 'suspend': Database::exec('UPDATE users SET writer_suspended_until=?,writer_suspension_reason=? WHERE id=?',[$duration['until'],$reason!==''?$reason:null,$id]); $this->restrictionLog($id,(int)$u['id'],'writer_suspension',$reason,$duration['until'],$previous,['until'=>$duration['until']]); break;
            case 'unsuspend': Database::exec('UPDATE users SET writer_suspended_until=NULL,writer_suspension_reason=NULL WHERE id=?',[$id]); $this->restrictionLog($id,(int)$u['id'],'writer_suspension_removed',$reason,null,$previous,['until'=>null]); break;
            case 'ban': Database::exec('UPDATE users SET login_banned_until=?,login_ban_reason=? WHERE id=?',[$duration['until'],$reason!==''?$reason:null,$id]); $this->restrictionLog($id,(int)$u['id'],'login_ban',$reason,$duration['until'],$previous,['until'=>$duration['until']]); break;
            case 'unban': Database::exec('UPDATE users SET login_banned_until=NULL,login_ban_reason=NULL WHERE id=?',[$id]); $this->restrictionLog($id,(int)$u['id'],'login_ban_removed',$reason,null,$previous,['until'=>null]); break;
            case 'hide': Database::exec('UPDATE users SET writer_directory_hidden=1 WHERE id=?',[$id]); audit_log('writer_profile_hidden','user',$id,$reason); break;
            case 'restore_profile': Database::exec('UPDATE users SET writer_directory_hidden=0 WHERE id=?',[$id]); audit_log('writer_profile_restored','user',$id,$reason); break;
            case 'trash': Database::exec('UPDATE users SET account_trash_previous_status=status,account_trashed_at=UTC_TIMESTAMP(),account_trashed_by=?,account_trash_reason=?,status=\'blocked\' WHERE id=?',[(int)$u['id'],$reason!==''?$reason:null,$id]); audit_log('writer_account_trashed','user',$id,$reason); break;
            case 'restore': Database::exec("UPDATE users SET status=COALESCE(NULLIF(account_trash_previous_status,''),'active'),account_trashed_at=NULL,account_trashed_by=NULL,account_trash_reason=NULL,account_trash_previous_status=NULL WHERE id=? AND account_trashed_at IS NOT NULL",[$id]); audit_log('writer_account_restored','user',$id,$reason); break;
            default: $this->redirect('/admin-v2/writers','الإجراء غير متاح.','error');
        }
        $this->redirect('/admin-v2/writers','تم تنفيذ الإجراء وتسجيله.');
    }

    public function administrators(): void
    {
        $u=$this->admin(); $rows=Database::all("SELECT id,name,username,email,status,two_factor_enabled,created_at,last_login_at FROM users WHERE role='admin' ORDER BY status DESC,created_at ASC");
        $this->render('administrators',compact('rows','u'),'حسابات الإدارة');
    }

    public function writerProfile(string $id): void
    {
        $u=$this->admin(); $id=(int)$id;
        $writer=Database::one("SELECT u.*,p.professional_title,p.cv_summary,p.location,p.website,p.linkedin,p.portfolio_url,p.is_public,p.show_contact,pt.display_name,pt.bio FROM users u LEFT JOIN writer_profiles p ON p.user_id=u.id LEFT JOIN writer_profile_translations pt ON pt.user_id=u.id AND pt.locale='ar' WHERE u.id=? AND u.role IN ('writer','trusted_writer') LIMIT 1",[$id]);
        if(!$writer)$this->redirect('/admin-v2/writers','حساب الكاتب غير موجود.','error');
        $articles=[];$thoughts=[];$awards=[];$history=[];
        try{$articles=Database::all("SELECT a.id,a.status,t.title,t.slug FROM articles a LEFT JOIN article_translations t ON t.article_id=a.id AND t.locale='ar' WHERE a.author_id=? ORDER BY a.updated_at DESC LIMIT 20",[$id]);}catch(\Throwable){}
        try{$thoughts=Database::all("SELECT k.id,k.status,t.body,t.title FROM khawatir k LEFT JOIN khawatir_translations t ON t.khawatir_id=k.id AND t.locale='ar' WHERE k.author_id=? ORDER BY k.updated_at DESC LIMIT 20",[$id]);}catch(\Throwable){}
        try{$awards=Database::all('SELECT * FROM writer_thanks WHERE user_id=? ORDER BY active DESC,created_at DESC LIMIT 50',[$id]);}catch(\Throwable){}
        try{$history=Database::all("SELECT action,details,created_at FROM audit_logs WHERE entity_type='user' AND entity_id=? ORDER BY id DESC LIMIT 30",[$id]);}catch(\Throwable){}
        $this->render('writer-profile-v2',compact('writer','articles','thoughts','awards','history','u'),'ملف الكاتب');
    }

    public function saveWriterAward(): void
    {
        $u=$this->admin();$this->csrf();$id=(int)($_POST['user_id']??0);$title=trim((string)($_POST['title']??''));$message=trim((string)($_POST['message']??''));$type=trim((string)($_POST['award_type']??'featured'));
        if(!$id||$title==='')$this->redirect('/admin-v2/writers/'. $id,'العنوان مطلوب.','error');
        $writer=Database::one("SELECT id FROM users WHERE id=? AND role IN ('writer','trusted_writer')",[$id]);if(!$writer)$this->redirect('/admin-v2/writers','الكاتب غير موجود.','error');
        Database::insert('INSERT INTO writer_thanks(user_id,title,message,locale,award_type,announce_on_site,active) VALUES(?,?,? ,\'ar\',?,0,1)',[$id,mb_substr($title,0,190),mb_substr($message,0,4000),mb_substr($type,0,40)]);audit_log('writer_award_created','user',$id,$title);$this->redirect('/admin-v2/writers/'.$id,'تم حفظ التكريم.');
    }

    public function deleteWriterAward(): void
    {
        $u=$this->admin();$this->csrf();$award=(int)($_POST['award_id']??0);$row=Database::one('SELECT user_id,title FROM writer_thanks WHERE id=?',[$award]);if(!$row)$this->redirect('/admin-v2/writers','التكريم غير موجود.','error');Database::exec('UPDATE writer_thanks SET active=0 WHERE id=?',[$award]);audit_log('writer_award_disabled','user',(int)$row['user_id'],(string)$row['title']);$this->redirect('/admin-v2/writers/'.(int)$row['user_id'],'تم إخفاء التكريم.');
    }

    public function administratorAction(): void
    {
        $u=$this->admin(); $this->csrf(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'');
        if($id===(int)$u['id'])$this->redirect('/admin-v2/administrators','لا يمكن تعطيل حسابك من هذه الصفحة.','error');
        $target=Database::one("SELECT id,status FROM users WHERE id=? AND role='admin' LIMIT 1",[$id]); if(!$target)$this->redirect('/admin-v2/administrators','الحساب غير موجود.','error');
        if($action==='disable'){
            $active=(int)(Database::one("SELECT COUNT(*) c FROM users WHERE role='admin' AND status='active'")['c']??0); if($active<=1)$this->redirect('/admin-v2/administrators','لا يمكن تعطيل آخر مدير نشط.','error');
            Database::exec("UPDATE users SET status='blocked' WHERE id=?",[$id]); audit_log('administrator_disabled','user',$id,'');
        }elseif($action==='enable'){ Database::exec("UPDATE users SET status='active' WHERE id=?",[$id]); audit_log('administrator_enabled','user',$id,''); }
        else $this->redirect('/admin-v2/administrators','إجراء غير متاح؛ تغيير الأدوار يحتاج صلاحية Super Administrator مستقلة.','error');
        $this->redirect('/admin-v2/administrators','تم تحديث حساب الإدارة.');
    }
}
