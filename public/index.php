<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Chrome address-bar / bookmark navigations omit Referer (Sec-Fetch-Site: none).
if (
    empty($_SERVER['HTTP_REFERER'] ?? null)
    && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'none'
    && ($_SERVER['HTTP_SEC_FETCH_MODE'] ?? '') === 'navigate'
) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $_SERVER['HTTP_REFERER'] = ($https ? 'https' : 'http') . '://' . $host . '/';
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
