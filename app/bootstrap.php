<?php
declare(strict_types=1);

if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');

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

// Environment values take precedence in Staging/Production. local.php remains
// a compatible local-development fallback and is never committed.
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

// Schema changes are convenient in local Laragon development, but a deployed
// request must never modify a live database merely because PHP was invoked.
// Production migrations require an explicit APP_RUN_MIGRATIONS=true setting.
$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$isLocalRequest = PHP_SAPI === 'cli' || preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/', $requestHost) === 1 || str_ends_with(preg_replace('/:\d+$/', '', $requestHost), '.test');
$migrationSetting = $environment('APP_RUN_MIGRATIONS');
$runSchemaMigrations = $migrationSetting === false ? $isLocalRequest : filter_var($migrationSetting, FILTER_VALIDATE_BOOL);

if (PHP_SAPI !== 'cli') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // The __Host- cookie prefix is valid only on HTTPS. Browsers reject it on
    // localhost/http, which caused every POST to lose its session and fail CSRF.
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
    if ($runSchemaMigrations && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'install.php')) {
        require_once BASE_PATH . '/app/migrations.php';
        run_schema_migrations();
        \App\Services\BehindPage::ensure();
        \App\Services\AboutPage::ensure();
        \App\Services\ManagedPages::ensure();
    }
}

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
