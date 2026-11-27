<?php
declare(strict_types=1);

// Routing: "/index.php" and "/?classic=1" serve the classic single-page dashboard below
// (unchanged, with its snapshot ETag/Last-Modified caching). Every other path ("/", "/overview",
// "/alerts", ...) goes to the front controller in src/front.php. The web server sends paths that
// are not real files here (nginx: try_files $uri $uri/ /index.php?$query_string; php -S: use
// scripts/dev-router.php), so static files under public/ are still served directly.
$signalopsRequestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$signalopsClassic = $signalopsRequestPath === '/index.php'
    || (($signalopsRequestPath === '/' || $signalopsRequestPath === '' || $signalopsRequestPath === null) && isset($_GET['classic']) && $_GET['classic'] !== '0');
if (!$signalopsClassic) {
    require dirname(__DIR__) . '/src/front.php';
}

$projectRoot = dirname(__DIR__);

require $projectRoot . '/app/bootstrap.php';

$config = load_signalops_config($projectRoot);
$status = collect_status($config);
$cacheMeta = is_array($status['_cache'] ?? null) ? $status['_cache'] : [];
$cacheConfig = is_array($config['cache'] ?? null) ? $config['cache'] : [];
$browserMaxAge = max(0, min(60, (int)($cacheConfig['browser_max_age'] ?? 5)));
$staleWhileRevalidate = max(60, min(3600, (int)($cacheConfig['stale_while_revalidate'] ?? 60)));
$cdnConfig = is_array($cacheConfig['cdn'] ?? null) ? $cacheConfig['cdn'] : [];
$cdnEnabled = (bool)($cdnConfig['enabled'] ?? false);
$cdnEdgeMaxAge = max(0, min(3600, (int)($cdnConfig['edge_max_age'] ?? 60)));
$cdnStaleWhileRevalidate = max(0, min(86400, (int)($cdnConfig['stale_while_revalidate'] ?? $staleWhileRevalidate)));
$cdnStaleIfError = max(0, min(604800, (int)($cdnConfig['stale_if_error'] ?? 604800)));
$serverTimingState = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cacheMeta['state'] ?? 'fresh')) ?: 'fresh';
$cacheAge = max(0, (int)($cacheMeta['age_seconds'] ?? 0));
$cacheCreatedAt = max(0, (int)($cacheMeta['created_at'] ?? time()));
$etag = '"' . sha1($cacheCreatedAt . '|' . $serverTimingState) . '"';
$lastModified = gmdate('D, d M Y H:i:s', $cacheCreatedAt) . ' GMT';

$refreshAfterResponse = static function () use ($cacheMeta, $projectRoot, $config): void {
    if (empty($cacheMeta['refresh_after_response'])) {
        return;
    }

    if (!spawn_status_cache_refresh($projectRoot, $config['cache'] ?? [])) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_flush();
            @flush();
        }

        ignore_user_abort(true);
        @set_time_limit(30);
        refresh_status_cache($config, (string)($cacheMeta['refresh_lock_path'] ?? ''), true);
    }
};

if ($cdnEnabled) {
    $cdnDirectives = 'public, max-age=' . $cdnEdgeMaxAge
        . ', stale-while-revalidate=' . $cdnStaleWhileRevalidate
        . ', stale-if-error=' . $cdnStaleIfError;
    header('Cache-Control: public, max-age=' . $browserMaxAge . ', s-maxage=' . $cdnEdgeMaxAge . ', stale-while-revalidate=' . $staleWhileRevalidate . ', stale-if-error=' . $cdnStaleIfError);
    header('CDN-Cache-Control: ' . $cdnDirectives);
    header('Cloudflare-CDN-Cache-Control: ' . $cdnDirectives);
} else {
    header('Cache-Control: private, max-age=' . $browserMaxAge . ', stale-while-revalidate=' . $staleWhileRevalidate);
}
header('Server-Timing: signalops_cache;desc="' . $serverTimingState . '";dur=' . $cacheAge);
header('X-SignalOps-Status-Cache: ' . $serverTimingState);
header('X-SignalOps-Status-Cache-Age: ' . $cacheAge);
header('ETag: ' . $etag);
header('Last-Modified: ' . $lastModified);

$requestEtags = array_filter(array_map(static function ($value): string {
    return trim(str_replace('W/', '', $value));
}, explode(',', (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))));
$etagMatches = in_array('*', $requestEtags, true) || in_array($etag, $requestEtags, true);
$ifModifiedSince = strtotime((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
$lastModifiedMatches = $ifModifiedSince !== false && $cacheCreatedAt <= $ifModifiedSince;

if ($etagMatches || (!$requestEtags && $lastModifiedMatches)) {
    http_response_code(304);
    $refreshAfterResponse();
    exit;
}

require $projectRoot . '/app/views/dashboard.php';
$refreshAfterResponse();
