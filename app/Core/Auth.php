<?php
namespace App\Core;
final class Auth {
    private static ?array $userCache=null;
    private const REMEMBER_SECONDS=2592000;
    public static function check(): bool { return self::user() !== null; }
    public static function user(): ?array {
        if(self::$userCache!==null) return self::$userCache ?: null;
        if(!empty($_SESSION['remember_until']) && (int)$_SESSION['remember_until']<time()){
            self::logout();
            return null;
        }
        $id=(int)($_SESSION['user_id'] ?? 0);
        if(!$id){ self::$userCache=[]; return null; }
        try { $u=Database::one('SELECT id,name,username,email,phone,role,status,bio,avatar,writer_verified,writer_honored,writer_vip,login_banned_until,account_trashed_at,writer_suspended_until,writer_suspension_reason,two_factor_enabled,created_at,last_login_at FROM users WHERE id=?',[$id]); } catch (\Throwable) { $u=Database::one('SELECT id,name,username,email,role,status,bio,avatar,created_at,last_login_at FROM users WHERE id=?',[$id]); if($u){ $u['phone']=''; $u['writer_verified']=0; $u['writer_honored']=0; $u['writer_vip']=0; $u['login_banned_until']=null; $u['account_trashed_at']=null; $u['writer_suspended_until']=null; $u['writer_suspension_reason']=null; $u['two_factor_enabled']=0; } }
        if($u && !empty($u['login_banned_until']) && strtotime((string)$u['login_banned_until'])>time()){
            self::logout();
            return null;
        }
        self::$userCache=$u ?: []; return $u;
    }
    public static function attempt(string $identifier,string $password): bool {
        $identifier=mb_strtolower(trim($identifier)); $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown'); $key=hash('sha256',$identifier.'|'.$ip); $throttle=\setting('security_login_throttle','1')==='1';
        $th=null;
        if($throttle){ try { $th=Database::one('SELECT attempts,locked_until FROM auth_throttles WHERE throttle_key=?',[$key]); } catch (\Throwable) {} }
        if($throttle && $th && !empty($th['locked_until']) && strtotime($th['locked_until'])>time()) return false;
        $u=Database::one('SELECT * FROM users WHERE email=? OR username=? LIMIT 1',[$identifier,$identifier]);
        $loginBanActive=$u && !empty($u['login_banned_until']) && strtotime((string)$u['login_banned_until'])>time();
        $ok=$u && !$loginBanActive && in_array((string)$u['status'],['active','pending'],true) && empty($u['account_trashed_at']) && password_verify($password,$u['password_hash']);
        if(!$ok){
            if($throttle){ $attempts=(int)($th['attempts']??0)+1; $locked=$attempts>=8?date('Y-m-d H:i:s',time()+900):null; try { if($th) Database::exec('UPDATE auth_throttles SET attempts=?,last_attempt_at=CURRENT_TIMESTAMP,locked_until=? WHERE throttle_key=?',[$attempts,$locked,$key]); else Database::insert('INSERT INTO auth_throttles(throttle_key,attempts,last_attempt_at,locked_until) VALUES(?,?,CURRENT_TIMESTAMP,?)',[$key,$attempts,$locked]); } catch (\Throwable) {} }
            if(\setting('security_audit_login','1')==='1') \audit_log('login_failed','auth',null,$identifier);
            return false;
        }
        if($throttle){ try { Database::exec('DELETE FROM auth_throttles WHERE throttle_key=?',[$key]); } catch (\Throwable) {} }
        session_regenerate_id(true);
        if (($u['role'] ?? '') === 'admin' && !empty($u['two_factor_enabled'])) {
            unset($_SESSION['user_id']);
            $_SESSION['two_factor_pending_user_id']=(int)$u['id'];
            $_SESSION['two_factor_pending_started']=time();
            self::$userCache=null;
            return true;
        }
        $_SESSION['user_id']=(int)$u['id']; self::$userCache=null;
        try { Database::exec('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?',[$u['id']]); } catch (\Throwable) {}
        if(\setting('security_audit_login','1')==='1') \audit_log('login_success','user',(int)$u['id'],$identifier);
        return true;
    }

    /** Establish a fresh session for an account that was just created internally.
     * This deliberately performs the same account-state checks as normal login. */
    public static function loginUserById(int $id): bool {
        if($id<1) return false;
        $u=Database::one('SELECT id,role,status,login_banned_until,account_trashed_at,two_factor_enabled FROM users WHERE id=? LIMIT 1',[$id]);
        if(!$u || !in_array((string)$u['status'],['active','pending'],true) || !empty($u['account_trashed_at'])) return false;
        if(!empty($u['login_banned_until']) && strtotime((string)$u['login_banned_until'])>time()) return false;
        // A newly created applicant cannot be an administrator, so no 2FA bypass is possible here.
        if(in_array((string)$u['role'],['admin','editor'],true)) return false;
        session_regenerate_id(true);
        unset($_SESSION['two_factor_pending_user_id'],$_SESSION['two_factor_pending_started'],$_SESSION['remember_after_two_factor']);
        $_SESSION['user_id']=(int)$u['id']; self::$userCache=null;
        try { Database::exec('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?',[(int)$u['id']]); } catch (\Throwable) {}
        if(\setting('security_audit_login','1')==='1') \audit_log('login_success','user',(int)$u['id'],'writer_application_auto_login');
        return true;
    }

    public static function setRememberMe(bool $remember): void {
        unset($_SESSION['remember_until'],$_SESSION['remember_after_two_factor']);
        if(!$remember || PHP_SAPI==='cli') return;
        $_SESSION['remember_until']=time()+self::REMEMBER_SECONDS;
        $cookie=session_get_cookie_params();
        setcookie(session_name(),session_id(),[
            'expires'=>(int)$_SESSION['remember_until'],
            'path'=>$cookie['path']??'/',
            'domain'=>$cookie['domain']??'',
            'secure'=>(bool)($cookie['secure']??false),
            'httponly'=>true,
            'samesite'=>'Lax',
        ]);
    }

    public static function twoFactorPending(): bool { return !empty($_SESSION['two_factor_pending_user_id']); }
    public static function pendingTwoFactorUser(): ?array {
        $id=(int)($_SESSION['two_factor_pending_user_id']??0); if(!$id)return null;
        if((int)($_SESSION['two_factor_pending_started']??0)<time()-600){ unset($_SESSION['two_factor_pending_user_id'],$_SESSION['two_factor_pending_started']); return null; }
        return Database::one('SELECT id,name,email,role,two_factor_secret,two_factor_recovery_codes,two_factor_enabled FROM users WHERE id=?',[$id]);
    }
    public static function completeTwoFactor(): void {
        $id=(int)($_SESSION['two_factor_pending_user_id']??0); if(!$id)return;
        $remember=!empty($_SESSION['remember_after_two_factor']);
        unset($_SESSION['two_factor_pending_user_id'],$_SESSION['two_factor_pending_started']);
        $_SESSION['user_id']=$id; self::$userCache=null; session_regenerate_id(true);
        self::setRememberMe($remember);
        try { Database::exec('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=?',[$id]); } catch (\Throwable) {}
    }

    public static function isImpersonating(): bool { return !empty($_SESSION['impersonator_id']); }
    public static function impersonatorId(): int { return (int)($_SESSION['impersonator_id']??0); }
    public static function startImpersonation(int $targetId): bool {
        $actor=self::user();
        if(!$actor || ($actor['role']??'')!=='admin' || self::isImpersonating()) return false;
        $target=Database::one("SELECT id,role,status FROM users WHERE id=? AND role IN ('writer','trusted_writer') AND status='active'",[$targetId]);
        if(!$target) return false;
        $_SESSION['impersonator_id']=(int)$actor['id'];
        $_SESSION['user_id']=(int)$target['id'];
        self::$userCache=null; session_regenerate_id(true); return true;
    }
    public static function stopImpersonation(): bool {
        $adminId=(int)($_SESSION['impersonator_id']??0); if(!$adminId) return false;
        $admin=Database::one("SELECT id FROM users WHERE id=? AND role='admin' AND status='active'",[$adminId]);
        if(!$admin){ unset($_SESSION['impersonator_id']); return false; }
        $_SESSION['user_id']=$adminId; unset($_SESSION['impersonator_id']); self::$userCache=null; session_regenerate_id(true); return true;
    }
    public static function logout(): void {
        $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); }
        session_destroy(); self::$userCache=null;
    }
    public static function checkRole(array $roles): bool { $u=self::user(); return $u && in_array($u['role'],$roles,true); }
    public static function writerPrivilegesSuspended(?array $user=null): bool {
        $u=$user??self::user();
        return (bool)($u && in_array((string)($u['role']??''),['writer','trusted_writer'],true) && !empty($u['writer_suspended_until']) && strtotime((string)$u['writer_suspended_until'])>time());
    }
    public static function requireRole(array $roles): void {
        if(!self::user()) redirect('/login');
        if(!self::checkRole($roles)){ http_response_code(403); exit('غير مصرح لك بالوصول إلى هذه الصفحة.'); }
    }
}
