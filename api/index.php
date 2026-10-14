<?php
declare(strict_types=1);

// Vercel PHP Community Runtime entry point. Keep the original front controller
// at the repository root so Apache/Laragon deployments continue unchanged.
$projectRoot = dirname(__DIR__);
chdir($projectRoot);

// Fallback for Vercel deployments where the community runtime does not publish
// the repository's root /assets directory as static output. Vercel's filesystem
// handler serves actual static files first; this handles otherwise-missing ones.
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (is_string($requestPath) && str_starts_with($requestPath, '/assets/')) {
    $assetRoot = realpath($projectRoot . '/assets');
    $assetPath = realpath($projectRoot . rawurldecode($requestPath));
    $extension = strtolower(pathinfo($assetPath ?: '', PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css', 'js' => 'application/javascript',
        'svg' => 'image/svg+xml', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp',
        'ico' => 'image/x-icon', 'woff' => 'font/woff',
        'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
        'avif' => 'image/avif',
    ];
    if ($assetRoot !== false && $assetPath !== false
        && str_starts_with($assetPath, $assetRoot . DIRECTORY_SEPARATOR)
        && is_file($assetPath) && isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        header('Content-Length: ' . filesize($assetPath));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
            readfile($assetPath);
        }
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset not found';
    exit;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
require $projectRoot . '/index.php';
