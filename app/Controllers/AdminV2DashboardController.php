<?php
namespace App\Controllers;

use App\Core\Database;

final class AdminV2DashboardController extends AdminV2BaseController
{
    public function dashboard(): void
    {
        $u = $this->admin();
        $stats = [
            'articles'=>0,'published'=>0,'pending_articles'=>0,'writers'=>0,
            'applications'=>0,'suspended'=>0,'admins'=>0,'comments'=>0,
        ];
        $queries = [
            'articles'=>"SELECT COUNT(*) c FROM articles",
            'published'=>"SELECT COUNT(*) c FROM articles WHERE status='published'",
            'pending_articles'=>"SELECT COUNT(*) c FROM articles WHERE status IN ('review','changes')",
            'writers'=>"SELECT COUNT(*) c FROM users WHERE role IN ('writer','trusted_writer') AND status='active' AND account_trashed_at IS NULL",
            'applications'=>"SELECT COUNT(*) c FROM writer_applications WHERE status IN ('pending','under_review','changes_requested')",
            'suspended'=>"SELECT COUNT(*) c FROM users WHERE role IN ('writer','trusted_writer') AND writer_suspended_until>UTC_TIMESTAMP()",
            'admins'=>"SELECT COUNT(*) c FROM users WHERE role='admin' AND status='active'",
            'comments'=>"SELECT COUNT(*) c FROM article_comments WHERE status='pending'",
        ];
        foreach ($queries as $key=>$sql) { try { $stats[$key]=(int)(Database::one($sql)['c']??0); } catch (\Throwable) {} }
        $activity=[];
        try { $activity=Database::all("SELECT a.action,a.target_type,a.target_id,a.details,a.created_at,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 12"); } catch (\Throwable) {}
        $this->render('dashboard', compact('stats','activity','u'), 'مركز الإدارة');
    }

    public function theme(): void
    {
        $u=$this->admin(); $this->csrf();
        $theme=(string)($_POST['theme']??'dark'); if(!in_array($theme,['dark','light'],true))$theme='dark';
        Database::exec('INSERT INTO admin_v2_preferences(user_id,theme) VALUES(?,?) ON DUPLICATE KEY UPDATE theme=VALUES(theme),updated_at=CURRENT_TIMESTAMP',[(int)$u['id'],$theme]);
        if(($_SERVER['HTTP_ACCEPT']??'')==='application/json'){ header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true,'theme'=>$theme]); return; }
        $this->redirect('/admin-v2','تم حفظ مظهر لوحة الإدارة.');
    }

    public function search(): void
    {
        $u=$this->admin(); $q=trim((string)($_GET['q']??'')); $results=['users'=>[],'articles'=>[],'applications'=>[],'audit'=>[]];
        if(mb_strlen($q)>=2){ $like='%'.$q.'%';
            try{$results['users']=Database::all("SELECT id,name,username,email,role,status FROM users WHERE name LIKE ? OR username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 30",[$like,$like,$like]);}catch(\Throwable){}
            try{$results['articles']=Database::all("SELECT a.id,a.status,t.title FROM articles a LEFT JOIN article_translations t ON t.article_id=a.id AND t.locale='ar' WHERE t.title LIKE ? OR t.slug LIKE ? ORDER BY a.id DESC LIMIT 30",[$like,$like]);}catch(\Throwable){}
            try{$results['applications']=Database::all("SELECT id,name,email,status FROM writer_applications WHERE name LIKE ? OR email LIKE ? OR username LIKE ? ORDER BY id DESC LIMIT 30",[$like,$like,$like]);}catch(\Throwable){}
            if($q==='audit' || stripos($q,'audit')!==false)try{$results['audit']=Database::all("SELECT action,target_type,target_id,created_at FROM audit_logs ORDER BY id DESC LIMIT 30");}catch(\Throwable){}
        }
        $this->render('search',compact('q','results','u'),'بحث الإدارة');
    }
    public function audit(): void
    {
        $u=$this->admin();
        $q=trim((string)($_GET['q']??''));
        $page=max(1,(int)($_GET['page']??1)); $perPage=50;
        $where=[]; $params=[];
        if($q!==''){
            $where[]='(a.action LIKE ? OR a.target_type LIKE ? OR a.details LIKE ? OR u.name LIKE ?)';
            array_push($params,'%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%');
        }
        $condition=$where?'WHERE '.implode(' AND ',$where):'';
        $total=(int)(Database::one("SELECT COUNT(*) c FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id $condition",$params)['c']??0);
        $offset=($page-1)*$perPage;
        $rows=Database::all("SELECT a.id,a.action,a.target_type,a.target_id,a.details,a.ip_address,a.created_at,u.name admin_name,u.username FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id $condition ORDER BY a.id DESC LIMIT $perPage OFFSET $offset",$params);
        $pages=max(1,(int)ceil($total/$perPage));
        $this->render('audit',compact('rows','q','page','pages','total','u'),'سجل الإجراءات');
    }
}
