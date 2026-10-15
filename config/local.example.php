<?php
// Copy this file to local.php only for local development. In Staging or
// Production prefer DB_* and APP_* environment variables so credentials and
// domains remain outside the deployed codebase.
return [
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'YOUR_DATABASE',
        'username' => 'YOUR_USERNAME',
        'password' => 'YOUR_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => 'https://your-domain.example',
        'debug' => false,
    ],
];

// Google OpenID Connect is configured through environment variables, never in
// this file: GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI.
// Example redirect URI: https://your-domain.example/auth/google/callback
