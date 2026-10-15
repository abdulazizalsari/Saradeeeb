<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only\n");exit(1);}
require dirname(__DIR__).'/app/bootstrap.php';
$opts=getopt('', ['user-id:','confirm:']);
$id=filter_var($opts['user-id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$expected=$id?'PROMOTE '.$id:'';
if(!$id || ($opts['confirm']??'')!==$expected){
    fwrite(STDERR,"Usage: php tools/bootstrap-super-admin.php --user-id=ID --confirm=\"PROMOTE ID\"\n");
    exit(2);
}
$user=App\Core\Database::one("SELECT id,name,username,email,role,status FROM users WHERE id=? LIMIT 1",[$id]);
if(!$user || $user['role']!=='admin' || $user['status']!=='active'){fwrite(STDERR,"Target must be one existing active administrator; nothing changed.\n");exit(3);}
echo "Target administrator: #{$user['id']} {$user['name']} @{$user['username']} <{$user['email']}>\n";
echo "This operation assigns Super Administrator and cannot be inferred automatically. Continue? [yes/no] ";
$answer=trim((string)fgets(STDIN));
if(!hash_equals('yes',strtolower($answer))){fwrite(STDERR,"Cancelled; nothing changed.\n");exit(4);}
try{
    $role=App\Core\Database::one("SELECT id FROM admin_roles WHERE slug='super_admin' LIMIT 1");
    if(!$role) throw new RuntimeException('RBAC roles are not initialized.');
    App\Core\Database::transaction(function()use($id,$role){
        App\Core\Database::exec('INSERT IGNORE INTO admin_user_roles(user_id,role_id,assigned_by) VALUES(?,?,NULL)',[$id,(int)$role['id']]);
        App\Core\Database::insert('INSERT INTO audit_logs(user_id,action,target_type,target_id,details,ip_address,user_agent) VALUES(NULL,?,?,?,?,?,?)',['admin_super_bootstrap','user',$id,'Explicit CLI bootstrap assignment','127.0.0.1','CLI']);
    });
    echo "Super Administrator assignment completed for the selected account.\n";
}catch(Throwable $e){fwrite(STDERR,"No changes committed: ".$e->getMessage()."\n");exit(5);}
