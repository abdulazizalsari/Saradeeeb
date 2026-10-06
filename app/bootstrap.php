<?php
declare(strict_types=1);

use App\Core\Database;

const BASE_PATH = __DIR__ . '/..';

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $relative = str_replace('\\', '/', substr($class, 4));
    $path = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($path)) require $path;
});

$appConfig = require BASE_PATH . '/config/app.php';
$localPath = BASE_PATH . '/config/local.php';

if (!is_file($localPath) && !str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'install.php')) {
    header('Location: /install.php');
    exit;
}

$localConfig = is_file($localPath) ? require $localPath : [];
$environment = static function (string $name): string|false {
    $value = getenv($name);
    return ($value === false || $value === '') ? false : $value;
};

$dbOverrides = [];
foreach (['driver' => 'DB_DRIVER', 'host' => 'DB_HOST', 'port' => 'DB_PORT', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD', 'charset' => 'DB_CHARSET'] as $key => $variable) {
    $value = $environment($variable);
    if ($value !== false) $dbOverrides[$key] = $key === 'port' ? (int) $value : $value;
}
$appOverrides = [];
if (($value = $environment('APP_BASE_URL')) !== false) $appOverrides['base_url'] = $value;
if (($value = $environment('APP_DEBUG')) !== false) $appOverrides['debug'] = filter_var($value, FILTER_VALIDATE_BOOL);
$localConfig['db'] = array_replace($localConfig['db'] ?? [], $dbOverrides);
$localConfig['app'] = array_replace($localConfig['app'] ?? [], $appOverrides);
$GLOBALS['config'] = array_replace_recursive($appConfig, $localConfig['app'] ?? []);
$GLOBALS['db_config'] = $localConfig['db'] ?? [];

if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $sessionName = (string)($GLOBALS['config']['session_name'] ?? 'saradeeeb_session');
    if (!$secure && str_starts_with($sessionName, '__Host-')) {
        $sessionName = substr($sessionName, 7) ?: 'saradeeeb_session';
    }
    session_name($sessionName);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

require BASE_PATH . '/app/helpers.php';

if (!empty($GLOBALS['db_config'])) {
    Database::init($GLOBALS['db_config']);
    if (!str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'install.php')) {
        require_once BASE_PATH . '/app/migrations.php';
        run_schema_migrations();
    }
}

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
