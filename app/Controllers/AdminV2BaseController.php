<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\AdminV2Rbac;
use App\Core\View;

abstract class AdminV2BaseController
{
    protected function admin(bool $content = false): array
    {
        Auth::requireRole($content ? ['admin','editor','assistant'] : ['admin']);
        $u = Auth::user();
        if (!$u || ($u['status'] ?? '') !== 'active' || !empty($u['account_trashed_at'])) {
            http_response_code(403);
            exit('غير مصرح بالوصول.');
        }
        $path=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
        $permission='dashboard.view';
        if(str_starts_with($path,'/admin-v2/audit')) $permission='audit.view';
        elseif(str_starts_with($path,'/admin-v2/applications')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'applications.review':'applications.view';
        elseif(str_starts_with($path,'/admin-v2/writers')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'writers.manage':'writers.view';
        elseif(str_starts_with($path,'/admin-v2/administrators')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'administrators.disable':'administrators.view';
        elseif(str_starts_with($path,'/admin-v2/articles')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'articles.edit':'articles.view';
        elseif(str_starts_with($path,'/admin-v2/translations')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'translations.assign':'translations.view';
        elseif(str_starts_with($path,'/admin-v2/thoughts')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'thoughts.edit':'thoughts.view';
        elseif(str_starts_with($path,'/admin-v2/roles')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'roles.manage':'roles.view';
        elseif(str_starts_with($path,'/admin-v2/team')) $permission='team.manage';
        elseif(str_starts_with($path,'/admin-v2/pages')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'pages.manage':'pages.view';
        elseif(str_starts_with($path,'/admin-v2/whatsapp')) $permission=$_SERVER['REQUEST_METHOD']==='POST'?'settings.manage':'settings.view';
        elseif(str_starts_with($path,'/admin-v2/search')) $permission='dashboard.view';
        elseif(str_starts_with($path,'/admin-v2/modules')) $permission='settings.view';
        AdminV2Rbac::require($u,$permission);
        return $u;
    }

    protected function render(string $view, array $data, string $title): void
    {
        $user = $data['u'] ?? Auth::user();
        $theme = 'dark';
        if ($user) {
            try { $theme = (string)(Database::one('SELECT theme FROM admin_v2_preferences WHERE user_id=?', [(int)$user['id']])['theme'] ?? 'dark'); } catch (\Throwable) {}
        }
        if (!in_array($theme, ['dark','light'], true)) $theme = 'dark';
        View::render('admin-v2/'.$view, $data + ['u'=>$user, 'adminTheme'=>$theme, 'title'=>$title], 'admin-v2');
    }

    protected function csrf(): void { Csrf::enforce(); }

    protected function redirect(string $path, string $message = '', string $kind = 'success'): never
    {
        if ($message !== '') flash($kind, $message);
        redirect($path);
    }

    protected function duration(): ?array
    {
        $value = filter_var($_POST['duration_value'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>8760]]);
        $unit = (string)($_POST['duration_unit'] ?? 'hours');
        if (!$value || !in_array($unit, ['hours','days'], true)) return null;
        $seconds = $value * ($unit === 'days' ? 86400 : 3600);
        return ['value'=>$value, 'unit'=>$unit, 'seconds'=>$seconds, 'until'=>gmdate('Y-m-d H:i:s', time()+$seconds)];
    }

    protected function restrictionLog(int $userId, int $adminId, string $action, ?string $reason, ?string $endsAt, array $previous = [], array $new = []): void
    {
        try { Database::insert('INSERT INTO admin_v2_restriction_history(user_id,admin_id,action,reason,starts_at,ends_at,previous_state,new_state) VALUES(?,?,?,?,UTC_TIMESTAMP(),?,?,?)', [$userId,$adminId,$action,$reason,$endsAt,json_encode($previous,JSON_UNESCAPED_UNICODE),json_encode($new,JSON_UNESCAPED_UNICODE)]); } catch (\Throwable) {}
        audit_log($action, 'user', $userId, trim((string)$reason));
    }
}
