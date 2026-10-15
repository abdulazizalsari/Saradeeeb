<?php
namespace App\Controllers;

use App\Core\AdminV2Rbac;
use App\Core\Database;

final class AdminV2TeamController extends AdminV2BaseController
{
    private function owner(): array
    {
        $u=$this->admin();
        if(!AdminV2Rbac::isOwner($u)){ http_response_code(403); exit('هذه الصفحة متاحة للمالك الرئيسي فقط.'); }
        return $u;
    }

    public function index(): void
    {
        $u=$this->owner();
        $members=Database::all("SELECT u.id,u.name,u.username,u.email,u.status,u.created_at,u.last_login_at,GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') role_names FROM users u LEFT JOIN admin_user_roles ur ON ur.user_id=u.id LEFT JOIN admin_roles r ON r.id=ur.role_id WHERE u.role='admin' GROUP BY u.id ORDER BY u.status DESC,u.created_at ASC");
        $roles=Database::all("SELECT id,slug,name FROM admin_roles WHERE slug IN ('administrator','assistant') ORDER BY FIELD(slug,'administrator','assistant')");
        $this->render('team',compact('members','roles','u'),'الفريق والصلاحيات');
    }

    public function create(): void
    {
        $u=$this->owner(); $this->csrf();
        $name=trim((string)($_POST['name']??'')); $username=strtolower(trim((string)($_POST['username']??''))); $email=strtolower(trim((string)($_POST['email']??''))); $password=(string)($_POST['password']??''); $roleSlug=(string)($_POST['role_slug']??'assistant');
        if($name===''||!preg_match('/^[a-z0-9_\.\-]{3,80}$/',$username)||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10||!in_array($roleSlug,['administrator','assistant'],true)) $this->redirect('/admin-v2/team','تحقق من الاسم واسم المستخدم والبريد وكلمة المرور (10 أحرف على الأقل).','error');
        if(Database::one('SELECT id FROM users WHERE email=? OR username=? LIMIT 1',[$email,$username])) $this->redirect('/admin-v2/team','البريد أو اسم المستخدم مستخدم مسبقاً.','error');
        $role=Database::one('SELECT id FROM admin_roles WHERE slug=? LIMIT 1',[$roleSlug]);
        if(!$role)$this->redirect('/admin-v2/team','الدور غير متاح.','error');
        try {
            Database::transaction(function()use($name,$username,$email,$password,$role,$u){
                $id=Database::insert("INSERT INTO users(name,username,email,password_hash,role,status) VALUES(?,?,?,?,'admin','active')",[$name,$username,$email,password_hash($password,PASSWORD_DEFAULT)]);
                Database::exec('INSERT INTO admin_user_roles(user_id,role_id,assigned_by) VALUES(?,?,?)',[$id,(int)$role['id'],(int)$u['id']]);
            });
        } catch(\Throwable) { $this->redirect('/admin-v2/team','تعذر إنشاء الحساب. لم يتم تغيير الحسابات الحالية.','error'); }
        audit_log('admin_team_member_created','user',null,'role='.$roleSlug);
        $this->redirect('/admin-v2/team','تم إنشاء عضو الفريق بالحساب نفسه ودون تكرار سجلات.');
    }

    public function status(): void
    {
        $u=$this->owner(); $this->csrf(); $id=(int)($_POST['user_id']??0); $status=(string)($_POST['status']??'');
        $target=Database::one("SELECT u.id,u.status,EXISTS(SELECT 1 FROM admin_user_roles ur JOIN admin_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.slug='super_admin') is_owner FROM users u WHERE u.id=? AND u.role='admin'",[$id]);
        if(!$target||$id===(int)$u['id']||(int)$target['is_owner'])$this->redirect('/admin-v2/team','لا يمكن تعطيل حساب المالك أو حسابك.','error');
        if(!in_array($status,['active','blocked'],true))$this->redirect('/admin-v2/team','حالة غير صالحة.','error');
        Database::exec('UPDATE users SET status=? WHERE id=?',[$status,$id]); audit_log($status==='active'?'admin_team_member_enabled':'admin_team_member_disabled','user',$id,'');
        $this->redirect('/admin-v2/team','تم تحديث حالة عضو الفريق.');
    }

    public function permissions(): void
    {
        $u=$this->owner(); $id=(int)($_GET['user_id']??0);
        $member=Database::one("SELECT id,name,username,email,status FROM users WHERE id=? AND role='admin' LIMIT 1",[$id]);
        if(!$member)$this->redirect('/admin-v2/team','عضو الفريق غير موجود.','error');
        $roles=Database::all('SELECT r.name,r.slug FROM admin_user_roles ur JOIN admin_roles r ON r.id=ur.role_id WHERE ur.user_id=?',[$id]);
        $permissions=AdminV2Rbac::permissions(); $roleSlugs=[]; foreach($roles as $role)$roleSlugs=array_merge($roleSlugs,AdminV2Rbac::rolePermissionSlugs((int)(Database::one('SELECT id FROM admin_roles WHERE slug=?',[$role['slug']])['id']??0)));
        $overrides=AdminV2Rbac::userPermissionOverrides($id); $overrideMap=[]; foreach($overrides as $row)$overrideMap[$row['slug']]=$row['effect'];
        $this->render('team-permissions',compact('member','roles','permissions','roleSlugs','overrideMap','u'),'تخصيص الصلاحيات');
    }

    public function savePermissions(): void
    {
        $u=$this->owner(); $this->csrf(); $id=(int)($_POST['user_id']??0); $member=Database::one("SELECT id FROM users WHERE id=? AND role='admin' LIMIT 1",[$id]);
        if(!$member||$id===(int)$u['id']||AdminV2Rbac::isOwner($member))$this->redirect('/admin-v2/team','لا يمكن تعديل صلاحيات المالك أو الحساب الحالي.','error');
        $effects=(array)($_POST['effect']??[]); $permissions=AdminV2Rbac::permissions();
        Database::transaction(function()use($id,$effects,$permissions,$u){
            Database::exec('DELETE FROM admin_user_permissions WHERE user_id=?',[$id]);
            foreach($permissions as $permission){$slug=(string)$permission['slug'];$effect=$effects[$slug]??'';if(in_array($effect,['allow','deny'],true))Database::exec('INSERT INTO admin_user_permissions(user_id,permission_id,effect,assigned_by) VALUES(?,?,?,?)',[$id,(int)$permission['id'],$effect,(int)$u['id']]);}
        });
        audit_log('admin_user_permissions_updated','user',$id,'count='.count($effects));
        $this->redirect('/admin-v2/team/permissions?user_id='.$id,'تم حفظ الصلاحيات الفردية وتدخل فوراً.');
    }
}
