<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use Throwable;

final class AdminCommunityController {
    private function guard(): array { Auth::requireRole(['admin','editor']); return Auth::user(); }
    private function adminGuard(): array { Auth::requireRole(['admin']); return Auth::user(); }

    public function index(): void {
        $u=$this->guard(); $tab=(string)($_GET['tab']??'topics');
        if(!in_array($tab,['topics','replies','categories','reports','moderation','dictionary','members','banned','pinned','featured','settings'],true))$tab='topics';
        $locales=enabled_locales();
        $data=['tab'=>$tab,'u'=>$u,'locales'=>$locales,'title'=>'المنتدى'];
        if(in_array($tab,['topics','featured','pinned'],true)){
            $where=$tab==='featured'?"WHERE t.featured_week=1":($tab==='pinned'?"WHERE t.pinned=1":"");
            $data['topics']=Database::all("SELECT t.*,u.name author_name,u.username,ct.name category_name FROM community_topics t JOIN users u ON u.id=t.author_id LEFT JOIN community_category_translations ct ON ct.category_id=t.category_id AND ct.locale=t.locale {$where} ORDER BY t.pinned DESC,t.last_activity_at DESC,t.id DESC LIMIT 150");
            $data['categories']=Database::all("SELECT c.id,ct.name,ct.locale FROM community_categories c LEFT JOIN community_category_translations ct ON ct.category_id=c.id ORDER BY c.sort_order,c.id,ct.locale");
        } elseif($tab==='replies'){
            $data['replies']=Database::all("SELECT r.*,u.name,u.username,t.title topic_title,t.slug,t.locale FROM community_replies r JOIN users u ON u.id=r.user_id JOIN community_topics t ON t.id=r.topic_id ORDER BY r.id DESC LIMIT 200");
        } elseif($tab==='categories'){
            $data['categories']=Database::all("SELECT c.*,ct.locale,ct.name,ct.slug,ct.description FROM community_categories c LEFT JOIN community_category_translations ct ON ct.category_id=c.id ORDER BY c.sort_order,c.id,ct.locale");
        } elseif($tab==='reports'){
            $data['reports']=Database::all("SELECT r.*,u.name reporter_name,rev.name reviewer_name FROM community_reports r JOIN users u ON u.id=r.user_id LEFT JOIN users rev ON rev.id=r.reviewed_by ORDER BY FIELD(r.status,'open','resolved','dismissed'),r.id DESC LIMIT 200");
        } elseif($tab==='moderation'){
            $data['events']=Database::all("SELECT e.*,u.name user_name,u.username FROM community_moderation_events e JOIN users u ON u.id=e.user_id ORDER BY FIELD(e.status,'open','approved','rejected','dismissed'),FIELD(e.severity,'block','review','warning'),e.id DESC LIMIT 200");
        } elseif($tab==='dictionary'){
            $this->adminGuard();
            $data['terms']=Database::all("SELECT t.*,u.name creator_name FROM community_moderation_terms t LEFT JOIN users u ON u.id=t.created_by ORDER BY t.active DESC,FIELD(t.severity,'block','review','warning'),t.id DESC");
        } elseif($tab==='members'){
            $data['members']=Database::all("SELECT u.id,u.name,u.username,u.email,u.role,u.status,u.created_at,(SELECT COUNT(*) FROM community_topics t WHERE t.author_id=u.id) topics_count,(SELECT COUNT(*) FROM community_replies r WHERE r.user_id=u.id AND r.status='visible') replies_count,(SELECT COUNT(*) FROM articles a WHERE a.author_id=u.id AND a.status='published') articles_count FROM users u ORDER BY u.id DESC LIMIT 200");
        } elseif($tab==='banned'){
            $data['banned']=Database::all("SELECT b.*,u.name,u.username,u.email,a.name banned_by FROM community_bans b JOIN users u ON u.id=b.user_id LEFT JOIN users a ON a.id=b.created_by ORDER BY b.id DESC");
        } elseif($tab==='settings'){
            $keys=['community_enabled','community_topics_per_page','community_replies_per_page','community_max_topics_hour','community_max_replies_hour','community_max_links_topic','community_heat_days','community_heat_warm','community_heat_hot','community_heat_blazing','community_email_notifications'];
            $data['settings']=[]; foreach($keys as $k)$data['settings'][$k]=setting($k,'');
        }
        View::render('admin/community',$data,'dashboard');
    }

    public function saveCategory(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $sort=(int)($_POST['sort_order']??0); $active=!empty($_POST['active'])?1:0;
        if($id>0) Database::exec('UPDATE community_categories SET sort_order=?,active=? WHERE id=?',[$sort,$active,$id]);
        else $id=Database::insert('INSERT INTO community_categories(sort_order,active) VALUES(?,?)',[$sort,$active]);
        foreach(enabled_locales() as $code=>$info){
            $name=trim((string)($_POST['name'][$code]??'')); if($name==='')continue;
            $slug=trim((string)($_POST['slug'][$code]??'')); if($slug==='')$slug=slugify($name); if($slug==='')$slug='section-'.$id;
            $description=trim((string)($_POST['description'][$code]??''));
            Database::exec("INSERT INTO community_category_translations(category_id,locale,name,slug,description) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),slug=VALUES(slug),description=VALUES(description)",[$id,$code,$name,$slug,$description]);
        }
        audit_log('community_category_saved','community_category',$id,''); flash('success','تم حفظ قسم المنتدى.'); redirect('/admin/community?tab=categories');
    }

    public function deleteCategory(): void {
        $this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); if($id<1)redirect('/admin/community?tab=categories');
        $count=(int)(Database::one('SELECT COUNT(*) c FROM community_topics WHERE category_id=?',[$id])['c']??0);
        if($count>0){Database::exec('UPDATE community_categories SET active=0 WHERE id=?',[$id]);flash('success','القسم يحتوي نقاشات، لذلك تم تعطيله بدل حذفه.');}
        else {Database::exec('DELETE FROM community_categories WHERE id=?',[$id]);flash('success','تم حذف القسم.');}
        redirect('/admin/community?tab=categories');
    }

    public function topicAction(): void {
        $u=$this->guard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??''); $topic=Database::one('SELECT * FROM community_topics WHERE id=?',[$id]); if(!$topic)redirect('/admin/community');
        switch($action){
            case 'close': Database::exec("UPDATE community_topics SET status='closed' WHERE id=?",[$id]); break;
            case 'open': Database::exec("UPDATE community_topics SET status='open' WHERE id=?",[$id]); break;
            case 'hide': Database::exec("UPDATE community_topics SET status='hidden' WHERE id=?",[$id]); break;
            case 'show': Database::exec("UPDATE community_topics SET status='open' WHERE id=?",[$id]); break;
            case 'pin': Database::exec('UPDATE community_topics SET pinned=1 WHERE id=?',[$id]); break;
            case 'unpin': Database::exec('UPDATE community_topics SET pinned=0 WHERE id=?',[$id]); break;
            case 'feature': Database::exec('UPDATE community_topics SET featured_week=0 WHERE locale=?',[$topic['locale']]); Database::exec('UPDATE community_topics SET featured_week=1 WHERE id=?',[$id]); break;
            case 'unfeature': Database::exec('UPDATE community_topics SET featured_week=0 WHERE id=?',[$id]); break;
            case 'move': $cat=(int)($_POST['category_id']??0); if($cat>0)Database::exec('UPDATE community_topics SET category_id=? WHERE id=?',[$cat,$id]); break;
            case 'edit': $title=trim((string)($_POST['title']??'')); $content=sanitize_html((string)($_POST['content']??'')); if($title!=='')Database::exec('UPDATE community_topics SET title=?,content=?,excerpt=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$title,$content,mb_substr(trim(strip_tags($content)),0,220),$id]); break;
            case 'warn': $message=trim((string)($_POST['message']??'راجع قواعد منتدى سراديب قبل مشاركتك القادمة.')); $this->notify((int)$topic['author_id'],'تنبيه من إدارة المنتدى',$message,'/community/topic/'.$topic['slug']); break;
            case 'delete': if(($u['role']??'')!=='admin'){flash('error','الحذف النهائي متاح لمدير النظام فقط.');break;} Database::exec('DELETE FROM community_topics WHERE id=?',[$id]); break;
        }
        audit_log('community_topic_'.$action,'community_topic',$id,''); flash('success','تم تنفيذ الإجراء على النقاش.'); redirect('/admin/community?tab=topics');
    }

    public function replyAction(): void {
        $u=$this->guard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??''); $reply=Database::one('SELECT r.*,t.slug,t.locale FROM community_replies r JOIN community_topics t ON t.id=r.topic_id WHERE r.id=?',[$id]); if(!$reply)redirect('/admin/community?tab=replies');
        if($action==='hide'){
            if($reply['status']==='visible') Database::exec('UPDATE community_topics SET replies_count=IF(replies_count>0,replies_count-1,0) WHERE id=?',[(int)$reply['topic_id']]);
            Database::exec("UPDATE community_replies SET status='hidden' WHERE id=?",[$id]);
        }
        elseif($action==='restore'){
            if($reply['status']!=='visible') Database::exec('UPDATE community_topics SET replies_count=replies_count+1 WHERE id=?',[(int)$reply['topic_id']]);
            Database::exec("UPDATE community_replies SET status='visible' WHERE id=?",[$id]);
        }
        elseif($action==='edit'){ $content=sanitize_html((string)($_POST['content']??'')); if(trim(strip_tags($content))!=='')Database::exec('UPDATE community_replies SET content=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$content,$id]); }
        elseif($action==='warn'){ $message=trim((string)($_POST['message']??'راجع قواعد منتدى سراديب قبل مشاركتك القادمة.')); $this->notify((int)$reply['user_id'],'تنبيه من إدارة المنتدى',$message,locale_url($reply['locale'],'community/topic/'.$reply['slug']).'#reply-'.$id); }
        elseif($action==='delete' && ($u['role']??'')==='admin'){ if($reply['status']==='visible')Database::exec('UPDATE community_topics SET replies_count=IF(replies_count>0,replies_count-1,0) WHERE id=?',[(int)$reply['topic_id']]); Database::exec('DELETE FROM community_replies WHERE id=?',[$id]); }
        audit_log('community_reply_'.$action,'community_reply',$id,''); flash('success','تم تنفيذ الإجراء على الرد.'); redirect('/admin/community?tab=replies');
    }

    public function reportAction(): void {
        $this->guard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'resolve'); $status=$action==='dismiss'?'dismissed':'resolved'; $u=Auth::user(); $r=Database::one('SELECT * FROM community_reports WHERE id=?',[$id]); if(!$r)redirect('/admin/community?tab=reports');
        Database::exec('UPDATE community_reports SET status=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?',[$status,(int)$u['id'],$id]);
        $this->notify((int)$r['user_id'],'تحديث على بلاغك','تمت مراجعة البلاغ الذي أرسلته وحالته الآن: '.($status==='resolved'?'تمت المعالجة':'تم إغلاق البلاغ'),'/community');
        audit_log('community_report_'.$status,'community_report',$id,''); flash('success','تم تحديث حالة البلاغ.'); redirect('/admin/community?tab=reports');
    }

    public function moderationAction(): void {
        $u=$this->guard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $action=(string)($_POST['action']??'');
        $event=Database::one('SELECT * FROM community_moderation_events WHERE id=?',[$id]); if(!$event || !in_array($action,['approve','reject'],true))redirect('/admin/community?tab=moderation');
        $approved=$action==='approve';
        if($event['content_type']==='topic') Database::exec("UPDATE community_topics SET moderation_status=?,status=? WHERE id=?",[$approved?'approved':'blocked',$approved?'open':'hidden',(int)$event['content_id']]);
        else { $reply=Database::one('SELECT topic_id,status FROM community_replies WHERE id=?',[(int)$event['content_id']]); if($reply){ Database::exec("UPDATE community_replies SET moderation_status=?,status=? WHERE id=?",[$approved?'approved':'blocked',$approved?'visible':'hidden',(int)$event['content_id']]); if($approved && $reply['status']!=='visible') Database::exec('UPDATE community_topics SET replies_count=replies_count+1,last_activity_at=CURRENT_TIMESTAMP WHERE id=?',[(int)$reply['topic_id']]); } }
        Database::exec("UPDATE community_moderation_events SET status=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP WHERE id=?",[$approved?'approved':'rejected',(int)$u['id'],$id]);
        $this->notify((int)$event['user_id'],'تحديث مراجعة المنتدى',$approved?'تمت مراجعة مشاركتك ونشرها في المنتدى.':'لم تُنشر مشاركتك بعد مراجعة فريق الإشراف. يمكنك إعادة صياغتها بما يوافق قواعد المنتدى.','/community');
        audit_log('community_moderation_'.$action,$event['content_type'],(int)$event['content_id'],'event='.$id); flash('success','تم تحديث قرار الإشراف.'); redirect('/admin/community?tab=moderation');
    }

    public function saveModerationTerm(): void {
        $u=$this->adminGuard(); Csrf::enforce(); $id=(int)($_POST['id']??0); $phrase=trim((string)($_POST['phrase']??'')); $severity=(string)($_POST['severity']??'review');
        if(!in_array($severity,['warning','review','block'],true))$severity='review'; if(mb_strlen($phrase)<2 || mb_strlen($phrase)>255){flash('error','أدخل عبارة من حرفين إلى 255 حرفًا.');redirect('/admin/community?tab=dictionary');}
        $normalized=\App\Services\CommunityModeration::normalize($phrase); $exception=trim((string)($_POST['exception_phrase']??'')); $notes=mb_substr(trim((string)($_POST['notes']??'')),0,500); $active=!empty($_POST['active'])?1:0;
        if($id>0) Database::exec('UPDATE community_moderation_terms SET phrase=?,normalized_phrase=?,exception_phrase=?,severity=?,active=?,notes=? WHERE id=?',[$phrase,$normalized,$exception?:null,$severity,$active,$notes?:null,$id]);
        else Database::insert('INSERT INTO community_moderation_terms(phrase,normalized_phrase,exception_phrase,severity,active,notes,created_by) VALUES(?,?,?,?,?,?,?)',[$phrase,$normalized,$exception?:null,$severity,$active,$notes?:null,(int)$u['id']]);
        audit_log('community_moderation_term_saved','community_moderation_term',$id,$phrase); flash('success','تم حفظ قاعدة الإشراف.'); redirect('/admin/community?tab=dictionary');
    }

    public function banUser(): void {
        $u=$this->guard(); Csrf::enforce(); $userId=(int)($_POST['user_id']??0); $reason=mb_substr(trim((string)($_POST['reason']??'')),0,500); $days=max(0,(int)($_POST['days']??0));
        $target=Database::one('SELECT role FROM users WHERE id=?',[$userId]); $actorRole=(string)($u['role']??''); if(!$target || $target['role']==='admin' || ($actorRole!=='admin' && !in_array($target['role'],['member','writer','trusted_writer'],true))){flash('error','لا يمكن حظر هذا الحساب من المنتدى.');redirect('/admin/community?tab=members');}
        $expires=$days>0?date('Y-m-d H:i:s',time()+$days*86400):null;
        Database::exec("INSERT INTO community_bans(user_id,reason,expires_at,created_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),expires_at=VALUES(expires_at),created_by=VALUES(created_by),created_at=CURRENT_TIMESTAMP",[$userId,$reason,$expires,(int)$u['id']]);
        $this->notify($userId,'تم تعليق المشاركة في المنتدى',$reason?:'تم تعليق إمكانية المشاركة في منتدى سراديب.','/community'); audit_log('community_user_banned','user',$userId,$reason); flash('success','تم حظر العضو من المشاركة في المنتدى.'); redirect('/admin/community?tab=banned');
    }
    public function unbanUser(): void { $this->guard(); Csrf::enforce(); $userId=(int)($_POST['user_id']??0); Database::exec('DELETE FROM community_bans WHERE user_id=?',[$userId]); audit_log('community_user_unbanned','user',$userId,''); flash('success','تم رفع الحظر.'); redirect('/admin/community?tab=banned'); }

    public function saveSettings(): void {
        $this->adminGuard(); Csrf::enforce();
        $values=[
            'community_enabled'=>!empty($_POST['community_enabled'])?'1':'0',
            'community_email_notifications'=>!empty($_POST['community_email_notifications'])?'1':'0',
            'community_topics_per_page'=>(string)max(6,min(30,(int)($_POST['community_topics_per_page']??15))),
            'community_replies_per_page'=>(string)max(10,min(80,(int)($_POST['community_replies_per_page']??30))),
            'community_max_topics_hour'=>(string)max(1,min(30,(int)($_POST['community_max_topics_hour']??5))),
            'community_max_replies_hour'=>(string)max(1,min(120,(int)($_POST['community_max_replies_hour']??30))),
            'community_max_links_topic'=>(string)max(0,min(20,(int)($_POST['community_max_links_topic']??5))),
            'community_heat_days'=>(string)max(1,min(90,(int)($_POST['community_heat_days']??7))),
            'community_heat_warm'=>(string)max(1,(int)($_POST['community_heat_warm']??30)),
            'community_heat_hot'=>(string)max(2,(int)($_POST['community_heat_hot']??100)),
            'community_heat_blazing'=>(string)max(3,(int)($_POST['community_heat_blazing']??300)),
        ];
        foreach($values as $k=>$v)Database::exec('INSERT INTO settings(`key`,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$k,$v]);
        audit_log('community_settings_saved','settings',null,''); flash('success','تم حفظ إعدادات المنتدى.'); redirect('/admin/community?tab=settings');
    }

    private function notify(int $userId,string $title,string $message,string $link): void { Database::insert("INSERT INTO notifications(user_id,type,title,message,link,is_read) VALUES(?, 'community_admin',?,?,?,0)",[$userId,$title,$message,$link]); }
}
