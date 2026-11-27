<?php
declare(strict_types=1);

// Router for PHP's built-in server that behaves like deploy/nginx/signalops-status.conf:
// real files under public/ are served as is, everything else goes to public/index.php
// (classic dashboard for /index.php and /?classic=1, the front controller for the rest).
//
//   php -S 127.0.0.1:8080 -t public scripts/dev-router.php
$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$public = dirname(__DIR__) . '/public';
$publicReal = realpath($public);
$file = is_string($path) ? realpath($public . rawurldecode($path)) : false;
if ($path !== '/' && $publicReal !== false && $file !== false && str_starts_with($file, $publicReal . '/') && is_file($file)
    && !str_ends_with($file, '.php')) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
require $public . '/index.php';
