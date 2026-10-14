<?php
declare(strict_types=1);

// Vercel PHP Community Runtime entry point. Keep the original front controller
// at the repository root so Apache/Laragon deployments continue unchanged.
$projectRoot = dirname(__DIR__);
chdir($projectRoot);
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $projectRoot . '/index.php';
