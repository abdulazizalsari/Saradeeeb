<?php
namespace App\Controllers;

use App\Core\AdminV2Rbac;
use App\Core\Database;

final class AdminV2RolesController extends AdminV2BaseController
{
    public function index(): void
    {
        $u=$this->admin(); $roles=AdminV2Rbac::roles(); $permissions=AdminV2Rbac::permissions();
        $admins=Database::all("SELECT u.id,u.name,u.username,u.email,u.status,GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') role_names FROM users u LEFT JOIN admin_user_roles ur ON ur.user_id=u.id LEFT JOIN admin_roles r ON r.id=ur.role_id WHERE u.role='admin' GROUP BY u.id ORDER BY u.status DESC,u.name");
        $selected=(int)($_GET['role']??($roles[0]['id']??0)); $selectedRole=null; $selectedSlugs=[];
        foreach($roles as $role) if((int)$role['id']===$selected){$selectedRole=$role;break;}
        if($selectedRole) $selectedSlugs=AdminV2Rbac::rolePermissionSlugs($selected);
        $this->render('roles-v2',compact('roles','permissions','selectedRole','selectedSlugs','admins','u'),'الأدوار والصلاحيات');
    }

    public function assign(): void
    {
        $u=$this->admin(); $this->csrf();
        $userId=(int)($_POST['user_id']??0); $roleId=(int)($_POST['role_id']??0);
        $target=Database::one("SELECT id,status FROM users WHERE id=? AND role='admin' LIMIT 1",[$userId]);
        $role=Database::one('SELECT id,slug FROM admin_roles WHERE id=? LIMIT 1',[$roleId]);
        if(!$target||!$role)$this->redirect('/admin-v2/roles','الحساب أو الدور غير موجود.','error');
        if($role['slug']===AdminV2Rbac::SUPER && !AdminV2Rbac::allows($u,'permissions.manage'))$this->redirect('/admin-v2/roles','لا تملك صلاحية تعيين Super Administrator.','error');
        $old=Database::all('SELECT r.slug FROM admin_user_roles ur JOIN admin_roles r ON r.id=ur.role_id WHERE ur.user_id=?',[$userId]);
        if($role['slug']!==AdminV2Rbac::SUPER && in_array('super_admin',array_column($old,'slug'),true)){
            $count=(int)(Database::one("SELECT COUNT(*) c FROM admin_user_roles ur JOIN admin_roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id WHERE r.slug='super_admin' AND u.status='active'")['c']??0);
            if($count<=1)$this->redirect('/admin-v2/roles','لا يمكن إزالة آخر Super Administrator نشط.','error');
        }
        Database::transaction(function()use($userId,$roleId){Database::exec('DELETE FROM admin_user_roles WHERE user_id=?',[$userId]);Database::exec('INSERT INTO admin_user_roles(user_id,role_id,assigned_by) VALUES(?,?,?)',[$userId,$roleId,(int)($_SESSION['user_id']??0)]);});
        audit_log('admin_role_assigned','user',$userId,'role_id='.$roleId);
        $this->redirect('/admin-v2/roles','تم تحديث دور الحساب وتسجيل العملية.');
    }

    public function save(): void
    {
        $u=$this->admin(); $this->csrf();
        $id=(int)($_POST['role_id']??0); $slug=preg_replace('/[^a-z0-9_\-]/','',strtolower(trim((string)($_POST['slug']??''))));
        $name=trim((string)($_POST['name']??'')); $description=trim((string)($_POST['description']??''));
        $selected=array_values(array_unique(array_filter((array)($_POST['permissions']??''),static fn($p)=>is_string($p)&&preg_match('/^[a-z0-9_.]+$/',$p))));
        if($name===''||($id===0&&$slug==='')) $this->redirect('/admin-v2/roles','أدخل اسم الدور ومعرّفه.','error');
        $role=$id?Database::one('SELECT * FROM admin_roles WHERE id=? LIMIT 1',[$id]):null;
        if($id && !$role) $this->redirect('/admin-v2/roles','الدور غير موجود.','error');
        if($role && (string)$role['slug']===AdminV2Rbac::SUPER) $this->redirect('/admin-v2/roles?role='.$id,'لا يمكن تعديل صلاحيات Super Administrator من هذه الواجهة.','error');
        $savedId=$id;
        try {
            Database::transaction(function()use($id,$role,$slug,$name,$description,$selected){
                if($id){ Database::exec('UPDATE admin_roles SET name=?,description=? WHERE id=?',[$name,$description!==''?$description:null,$id]); $roleId=$id; }
                else { $roleId=Database::insert('INSERT INTO admin_roles(slug,name,description,is_system) VALUES(?,?,?,0)',[$slug,$name,$description!==''?$description:null]); }
                Database::exec('DELETE FROM admin_role_permissions WHERE role_id=?',[$roleId]);
                foreach($selected as $perm){$p=Database::one('SELECT id FROM admin_permissions WHERE slug=?',[$perm]); if($p) Database::exec('INSERT INTO admin_role_permissions(role_id,permission_id) VALUES(?,?)',[$roleId,(int)$p['id']]);}
            });
            if(!$savedId){$created=Database::one('SELECT id FROM admin_roles WHERE slug=?',[$slug]); $savedId=(int)($created['id']??0);}
            audit_log($id?'admin_role_updated':'admin_role_created','admin_role',$id?:null,$slug);
        } catch(\Throwable $e) { $this->redirect('/admin-v2/roles','تعذر حفظ الدور، ربما المعرّف مستخدم.','error'); }
        $this->redirect('/admin-v2/roles?role='.$savedId,'تم حفظ الدور وتسجيل العملية.');
    }
}
