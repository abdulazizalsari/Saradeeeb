<?php
namespace App\Core;

final class AdminV2Rbac
{
    public const SUPER='super_admin';
    public const ADMIN='administrator';
    public const EDITOR='editor';
    public const MODERATOR='content_moderator';

    public static function allows(array $user, string $permission): bool
    {
        // Bootstrap administrators must be able to open the RBAC screen before
        // the first role assignment is created. Without this exception the
        // roles page locked out the only available admin during installation.
        if (($user['role'] ?? '') === 'admin' && !self::hasAssignments()) return true;
        if (!self::hasAssignments() && in_array($permission,['roles.view','roles.manage','permissions.manage','administrators.create','administrators.edit','accounts.permanently_delete'],true)) return false;
        if (($user['role'] ?? '') === 'admin' && !self::hasAssignmentsFor((int)$user['id'])) return self::roleAllows(self::ADMIN,$permission);
        if (($user['role'] ?? '') === 'editor' && !self::hasAssignmentsFor((int)$user['id'])) return in_array($permission,['dashboard.view','articles.view','articles.create','articles.edit','articles.publish','thoughts.view','thoughts.create','thoughts.edit','thoughts.publish'],true);
        try {
            $override=Database::one('SELECT effect FROM admin_user_permissions up JOIN admin_permissions p ON p.id=up.permission_id WHERE up.user_id=? AND p.slug=? LIMIT 1',[(int)$user['id'],$permission]);
            if($override) return (string)$override['effect']==='allow';
            $row=Database::one('SELECT 1 FROM admin_user_roles ur JOIN admin_role_permissions rp ON rp.role_id=ur.role_id JOIN admin_permissions p ON p.id=rp.permission_id WHERE ur.user_id=? AND p.slug=? LIMIT 1',[(int)$user['id'],$permission]);
            return (bool)$row;
        } catch (\Throwable) { return false; }
    }

    public static function hasAssignments(): bool
    {
        try { return (int)(Database::one('SELECT COUNT(*) c FROM admin_user_roles')['c']??0)>0; } catch (\Throwable) { return false; }
    }
    private static function hasAssignmentsFor(int $userId): bool
    { try { return (bool)Database::one('SELECT 1 FROM admin_user_roles WHERE user_id=? LIMIT 1',[$userId]); } catch (\Throwable) { return false; } }
    private static function roleAllows(string $role,string $permission): bool
    { try { return (bool)Database::one('SELECT 1 FROM admin_role_permissions rp JOIN admin_roles r ON r.id=rp.role_id JOIN admin_permissions p ON p.id=rp.permission_id WHERE r.slug=? AND p.slug=? LIMIT 1',[$role,$permission]); } catch (\Throwable) { return false; } }

    public static function roles(): array
    { try { return Database::all('SELECT r.*,COUNT(DISTINCT ur.user_id) assigned_count FROM admin_roles r LEFT JOIN admin_user_roles ur ON ur.role_id=r.id GROUP BY r.id ORDER BY r.is_system DESC,r.id'); } catch (\Throwable) { return []; } }
    public static function permissions(): array
    { try { return Database::all('SELECT * FROM admin_permissions ORDER BY group_name,slug'); } catch (\Throwable) { return []; } }
    public static function rolePermissionSlugs(int $roleId): array
    { try { return array_map(static fn(array $r): string=>(string)$r['slug'],Database::all('SELECT p.slug FROM admin_role_permissions rp JOIN admin_permissions p ON p.id=rp.permission_id WHERE rp.role_id=?',[$roleId])); } catch (\Throwable) { return []; } }

    public static function isOwner(array $user): bool
    { try { return (bool)Database::one("SELECT 1 FROM admin_user_roles ur JOIN admin_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug='super_admin' LIMIT 1",[(int)($user['id']??0)]); } catch (\Throwable) { return false; } }

    public static function userPermissionOverrides(int $userId): array
    { try { return Database::all('SELECT p.slug,up.effect FROM admin_user_permissions up JOIN admin_permissions p ON p.id=up.permission_id WHERE up.user_id=? ORDER BY p.group_name,p.slug',[$userId]); } catch (\Throwable) { return []; } }
    public static function require(array $user,string $permission): void
    { if (!self::allows($user,$permission)) { http_response_code(403); exit('غير مصرح بهذه العملية.'); } }
}
