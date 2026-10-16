<?php
declare(strict_types=1);
/**
 * Hostinger SSH / CLI-only credential recovery. Nothing runs automatically.
 * Usage: php tools/reset-admin-password.php admin
 * This script does not load app/bootstrap.php, so it cannot run schema migrations.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$username = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_.-]{1,120}$/D', $username)) {
    fwrite(STDERR, "Usage: php tools/reset-admin-password.php USERNAME\n");
    exit(2);
}
$path = dirname(__DIR__) . '/config/local.php';
if (!is_file($path)) { fwrite(STDERR,"The existing config/local.php is required.\n"); exit(3); }
$local = require $path;
$cfg = $local['db'] ?? [];
$vars = ['host'=>'DB_HOST','port'=>'DB_PORT','database'=>'DB_DATABASE','username'=>'DB_USERNAME','password'=>'DB_PASSWORD','charset'=>'DB_CHARSET'];
foreach($vars as $field=>$key){$v=getenv($key);if($v!==false && $v!=='')$cfg[$field]=$v;}
if (($cfg['driver']??'mysql') !== 'mysql' || empty($cfg['database']) || empty($cfg['username'])) {
    fwrite(STDERR,"MySQL database settings are not configured.\n"); exit(4);
}
// Never receive a secret from argv: process arguments and shell history are visible.
$sttyAvailable = function_exists('shell_exec') && is_resource(STDIN) && function_exists('stream_isatty') && stream_isatty(STDIN) && trim((string)@shell_exec('command -v stty 2>/dev/null'))!=='';
if($sttyAvailable) @shell_exec('stty -echo');
try {
    fwrite(STDOUT,"New password (at least 12 characters): ");
    $password = rtrim((string)fgets(STDIN), "\r\n");
    fwrite(STDOUT,"\nConfirm new password: ");
    $confirm = rtrim((string)fgets(STDIN), "\r\n");
    fwrite(STDOUT,"\n");
} finally {
    if($sttyAvailable) @shell_exec('stty echo');
}
if(strlen($password)<12 || !hash_equals($password,$confirm)){
    fwrite(STDERR,"Password is too short or confirmation does not match. No changes made.\n");
    exit(5);
}
try {
    $pdo = new PDO('mysql:host='.$cfg['host'].';port='.(int)($cfg['port']??3306).';dbname='.$cfg['database'].';charset='.($cfg['charset']??'utf8mb4'),
        $cfg['username'],$cfg['password']??'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $pdo->beginTransaction();
    $check=$pdo->prepare('SELECT id FROM users WHERE username=? AND role=\'admin\' LIMIT 1 FOR UPDATE');
    $check->execute([$username]);
    $row=$check->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('No administrator with that username.');
    $algo=in_array('argon2id',password_algos(),true)?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;
    $hash=password_hash($password,$algo);
    $stmt=$pdo->prepare('UPDATE users SET password_hash=?,password_reset_token_hash=NULL,password_reset_expires_at=NULL WHERE id=?');
    $stmt->execute([$hash,(int)$row['id']]);
    $pdo->commit();
    fwrite(STDOUT,"Administrator password successfully updated.\n");
} catch(Throwable $e) {
    if(isset($pdo) && $pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,"Failed: ".$e->getMessage()."\n"); exit(6);
}
