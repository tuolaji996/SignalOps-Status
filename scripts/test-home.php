<?php
declare(strict_types=1);

/*
 * MONITOR area tests: StatusModel on demo data and on a cache file, snapshot states, every home
 * route rendered through the Kernel (en + zh-CN), /overview/live JSON, the /alerts export reader
 * and its demo/login behaviour, lang key parity, and the classic dashboard still rendering.
 *
 *   php scripts/test-home.php      (exits non-zero on failure)
 */

ob_start();
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/src/autoload.php';

use SignalOps\Status\Alerts\AlertState;
use SignalOps\Status\Config\Config;
use SignalOps\Status\Home\StatusModel;
use SignalOps\Status\Home\StatusSnapshot;
use SignalOps\Status\Http\Request;
use SignalOps\Status\I18n\I18n;
use SignalOps\Status\Kernel;
use SignalOps\Status\View\Preferences;

error_reporting(E_ALL);
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    throw new ErrorException($msg, 0, $no, $file, $line);
});
$root = dirname(__DIR__);

$tmp = rtrim(sys_get_temp_dir(), '/') . '/signalops-test-home-' . getmypid() . '-' . bin2hex(random_bytes(3));
mkdir($tmp . '/sessions', 0700, true);
ini_set('session.save_path', $tmp . '/sessions');
ini_set('session.cache_limiter', '');
ini_set('log_errors', '1');
ini_set('error_log', $tmp . '/php-errors.log');

$failures = 0;
$passes = 0;
$log = [];
function check(bool $ok, string $what): void
{
    global $failures, $passes, $log;
    if ($ok) {
        $passes++;
    } else {
        $failures++;
        $log[] = 'FAIL ' . $what;
    }
}

function req(string $method, string $uri, array $post = [], array $cookies = []): Request
{
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
    parse_str((string)(parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
    return new Request($method, Request::normalizePath($path), $query, $post, $cookies, ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri]);
}

$now = time();
$en = new I18n('en', $root . '/lang');

// ---- StatusModel on demo data -------------------------------------------------------------------
$demoConfig = Config::fromArray(['app' => ['demo' => true]]);
$snap = StatusSnapshot::read($demoConfig->all(), $now);
check($snap['state'] === 'ok' && $snap['demo'] === true && $snap['cache_state'] === 'fresh', 'demo snapshot is ok/fresh');
$model = (new StatusModel($en))->build($snap, $now);
check(count($model['machines']) === 6, 'demo: 6 machines');
check($model['probes'] === ['online' => 6, 'total' => 6], 'demo: all probes online');
check($model['overall']['level'] === 'ok', 'demo: overall ok (' . implode('; ', $model['overall']['reasons']) . ')');
check(count($model['kpis']) === 8, 'demo: 8 KPIs');
foreach ($model['kpis'] as $kpi) {
    check($kpi['value'] !== $en->t('common.value.none'), 'demo KPI has a value: ' . $kpi['key']);
}
check(count($model['latency']['nodes']) === 6 && count($model['latency']['links']) === 15, 'demo: full 6x6 latency mesh (15 links)');
$cells = 0;
foreach ($model['latency']['nodes'] as $from => $_) {
    foreach ($model['latency']['nodes'] as $to => $_n) {
        if ($from !== $to && isset($model['latency']['matrix'][$from][$to])) {
            $cells++;
        }
    }
}
check($cells === 30, 'demo: every matrix cell has a value');
check($model['latency']['median'] !== null, 'demo: median latency');
check($model['journal']['recovered'] === true && $model['journal']['tone'] === 'ok' && count($model['journal']['entries']) === 2, 'demo: journal shows a recovered incident with entries');
check(count($model['events']) === 8 && $model['events'][0]['symbol'] === 'NVDA' && $model['events'][0]['time'] !== null, 'demo: events with symbol and time');
check(count($model['archive']['kinds']) === 4 && $model['archive']['symbols'] === '932', 'demo: archive breakdown + symbol index');
check(isset($model['endpoints']['bot'], $model['endpoints']['api']) && $model['endpoints']['bot']['tone'] === 'ok', 'demo: endpoints');
check($model['traffic']['today'] !== $en->t('common.value.none'), 'demo: traffic summary');
$db = null;
foreach ($model['machines'] as $m) {
    if ($m['key'] === 'db') {
        $db = $m;
    }
}
check($db !== null && count($db['disks']) === 2 && $db['disk_tone'] === 'warn', 'demo: db has a second disk above the threshold');
check($db !== null && $db['role'] !== '' && $db['cpu_model'] !== null && $db['uptime'] !== null && $db['services'] !== [], 'demo: machine detail fields');
check(array_key_exists('strip.overall', $model['live']) && array_key_exists('kpi.archive', $model['live']), 'live map has strip + kpi keys');
check(json_encode($model['live']) !== false, 'live map is JSON-serialisable');

// ---- Snapshot states ----------------------------------------------------------------------------
$missing = StatusSnapshot::read(['app' => ['demo' => false], 'cache' => ['path' => $tmp . '/nope.json']], $now);
check($missing['state'] === 'missing', 'missing cache file -> missing');
$mm = (new StatusModel($en))->build($missing, $now);
check($mm['overall']['level'] === 'warn' && $mm['overall']['reasons'] === [$en->t('home.reason.no_snapshot')], 'missing snapshot -> warn + reason');
check($mm['machines'] === [] && $mm['latency']['enabled'] === false, 'missing snapshot -> empty model');

// Cache file as written by refresh_status_cache() (status + _meta.created_at).
$status = demo_status(['app' => ['demo' => true]]);
unset($status['_cache']);
$status['_meta'] = ['created_at' => $now - 200];
write_json_file($tmp . '/cache.json', $status);
$cfg = ['app' => ['demo' => false], 'cache' => ['path' => $tmp . '/cache.json', 'seconds' => 90, 'stale_seconds' => 300]];
$s = StatusSnapshot::read($cfg, $now);
check($s['state'] === 'ok' && $s['cache_state'] === 'stale' && $s['age'] === 200 && !isset($s['status']['_meta']), 'cache file (_meta format) -> stale snapshot');
$s = StatusSnapshot::read(['cache' => $cfg['cache'] + []] + $cfg, $now + 1000);
check($s['cache_state'] === 'expired', 'old cache file -> expired');
check((new StatusModel($en))->build($s, $now + 1000)['overall']['level'] === 'warn', 'expired snapshot -> warn');
file_put_contents($tmp . '/env.json', json_encode(['created_at' => $now - 10, 'status' => $status]));
$s = StatusSnapshot::read(['app' => ['demo' => false], 'cache' => ['path' => $tmp . '/env.json']], $now);
check($s['state'] === 'ok' && $s['cache_state'] === 'fresh', 'cache file ({created_at, status} envelope) -> fresh');

// A degraded snapshot: probe down, packet loss, endpoint offline, journal errors.
$bad = $status;
$bad['machines'][1]['probe']['ok'] = false;
$bad['machines'][1]['probe']['error'] = 'ssh: connect to host 192.0.2.10 port 22: timed out';
$bad['machines'][1]['sla']['last_ok'] = false;
$bad['endpoints']['api']['ok'] = false;
$bad['machines'][2]['probe']['journal'] = ['ok' => true, 'warning_count' => 1, 'error_count' => 3, 'latest_at' => gmdate('c', $now - 60), 'latest' => [['time' => gmdate('c', $now - 60), 'priority' => 3, 'message' => 'token=abc123 failed']]];
$bm = (new StatusModel($en))->build(['state' => 'ok', 'status' => $bad, 'created_at' => $now, 'age' => 0, 'cache_state' => 'fresh'], $now);
check($bm['overall']['level'] === 'bad' && $bm['overall']['tone'] === 'warn', 'degraded -> bad level, amber tone');
check(count($bm['overall']['reasons']) >= 3, 'degraded -> reasons listed');
$errText = '';
foreach ($bm['machines'] as $m) {
    $errText .= (string)$m['error'];
}
check(!str_contains($errText, '192.0.2.10') && str_contains($errText, '[private-host]'), 'probe errors are redacted');
check($bm['journal']['entries'][0]['kind'] === 'error' && !str_contains($bm['journal']['entries'][0]['message'], 'abc123'), 'journal errors shown and redacted');

// ---- Classic dashboard still renders ------------------------------------------------------------
$classic = (static function () use ($root): string {
    $config = load_signalops_config($root);
    $config['app']['demo'] = true;
    $status = collect_status($config);
    ob_start();
    require $root . '/app/views/dashboard.php';
    return (string)ob_get_clean();
})();
check(str_contains($classic, '</html>') && str_contains($classic, 'Recovered incident') && str_contains($classic, 'Database Server'), 'classic dashboard renders demo data');
check(str_contains($classic, 'All systems operational'), 'classic dashboard: demo overall ok');

// ---- Routes through the Kernel ------------------------------------------------------------------
$kernel = static fn (array $config): Kernel => new Kernel($root, Config::fromArray($config));
$demoKernel = $kernel(['app' => ['demo' => true]]);
$pages = [
    '/' => 'data-home-live',
    '/overview' => 'id="servers"',
    '/servers' => 'id="server-db"',
    '/latency' => 'data-lat-filter="app"',
    '/latency?view=map' => 'data-view="map"',
    '/logs' => 'id="probe-errors"',
    '/events' => 'id="events-table"',
    '/alerts' => 'class="alert-list"',
];
$liveJson = $demoKernel->handle(req('GET', '/overview/live'));
$live = json_decode($liveJson->body, true);
check($liveJson->status === 200 && is_array($live['values'] ?? null), '/overview/live returns JSON');
check(str_starts_with((string)($liveJson->headers['Content-Type'] ?? ''), 'application/json'), '/overview/live content type');
check(($liveJson->headers['X-SignalOps-Status-Cache'] ?? '') === 'demo', '/overview/live cache header says demo');
foreach (['en', 'zh-CN'] as $lang) {
    foreach ($pages as $uri => $marker) {
        $res = $demoKernel->handle(req('GET', $uri, [], [Preferences::LANG_COOKIE => $lang]));
        $body = $res->body;
        check($res->status === 200, "$lang $uri -> 200 (got {$res->status})");
        check(str_contains($body, $marker), "$lang $uri contains $marker");
        check(preg_match('/\bhome\.[a-z_]+\.[a-z_.]+/', strip_tags($body), $mk) !== 1, "$lang $uri has no untranslated home.* key" . (isset($mk[0]) ? ' (' . $mk[0] . ')' : ''));
        check(!preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $body), "$lang $uri has no PHP warnings");
        preg_match_all('/data-live(?:-tone|-meter|-bar)?="([^"]+)"/', $body, $mm);
        $unknown = array_diff(array_unique($mm[1]), array_keys($live['values']));
        check($unknown === [], "$lang $uri data-live keys exist in /overview/live" . ($unknown ? ': ' . implode(', ', array_slice($unknown, 0, 5)) : ''));
        if ($lang === 'zh-CN' && $uri === '/overview') {
            check(str_contains($body, '服务器资源监控'), 'zh-CN overview is translated');
        }
    }
}
$res = $demoKernel->handle(req('GET', '/overview'));
check(($res->headers['X-SignalOps-Status-Cache'] ?? '') === 'demo', 'overview cache header');
$body = $res->body;
foreach (['Server Resource Monitor', 'Latency Mesh', 'Service Health', 'Bot Error Journal', 'Archive Breakdown', 'Traffic Summary', 'Latest Events', 'Runtime Details', 'Recovered incident'] as $section) {
    check(str_contains($body, $section), 'overview shows ' . $section);
}
check(!str_contains($body, 'AI Decision'), 'overview has no AI journal block');

// Missing snapshot (demo off, no cache file): pages still render with a notice.
$emptyKernel = $kernel(['app' => ['demo' => false], 'cache' => ['path' => $tmp . '/none.json']]);
foreach (['/overview', '/servers', '/latency', '/logs', '/events'] as $uri) {
    $res = $emptyKernel->handle(req('GET', $uri));
    check($res->status === 200 && str_contains($res->body, $en->t('home.snapshot.missing_title')), "no snapshot: $uri renders with notice");
}
// Real cache file.
$fileKernel = $kernel(['app' => ['demo' => false], 'cache' => ['path' => $tmp . '/cache.json', 'seconds' => 90, 'stale_seconds' => 300]]);
$res = $fileKernel->handle(req('GET', '/servers'));
check($res->status === 200 && str_contains($res->body, 'Heatmap Server') && ($res->headers['X-SignalOps-Status-Cache'] ?? '') === 'stale', 'cache file: /servers renders, header stale');

// ---- Alerts ---------------------------------------------------------------------------------------
$demoAlerts = AlertState::demo($now);
check($demoAlerts['state'] === 'ok' && count($demoAlerts['active']) === 2 && count($demoAlerts['recent']) === 4 && !$demoAlerts['checker']['stale'], 'demo alerts parse');
check($demoAlerts['active'][0]['severity'] === 'P2', 'active alerts sorted by severity');
check(AlertState::load($tmp . '/no-alerts.json', $now)['state'] === 'missing', 'alerts: missing file');
file_put_contents($tmp . '/bad.json', '{nope');
check(AlertState::load($tmp . '/bad.json', $now)['state'] === 'invalid', 'alerts: invalid JSON');
file_put_contents($tmp . '/schema.json', json_encode(['schema' => 'other']));
check(AlertState::load($tmp . '/schema.json', $now)['state'] === 'schema', 'alerts: wrong schema');
$export = demo_alerts($now - 3600);
$export['active'][] = ['id' => 'x', 'severity' => 'P9', 'since' => $now - 10];
$export['active'][] = ['title' => 'no id'];
$export['recent'][] = ['kind' => 'other', 'id' => 'y'];
file_put_contents($tmp . '/alerts.json', json_encode($export));
$st = AlertState::load($tmp . '/alerts.json', $now, 300);
check($st['state'] === 'ok' && count($st['active']) === 3 && $st['checker']['stale'] === true, 'alerts: file parsed, bad rows dropped, stale checker');
check($st['active'][0]['id'] === 'x' && $st['active'][0]['severity'] === 'P1', 'alerts: unknown severity counts as P1 and sorts first');
check(count($st['recent']) === 4, 'alerts: unknown kinds dropped');

// Demo mode, no password: open. Demo off, no password: 503 login page. With password: 401.
$res = $demoKernel->handle(req('GET', '/alerts?filter=recovered'));
check($res->status === 200 && str_contains($res->body, 'discord-bot.service was not active') && !str_contains($res->body, 'Status page deployed'), 'demo /alerts filter=recovered');
$res = $kernel(['app' => ['demo' => false]])->handle(req('GET', '/alerts'));
check($res->status === 503, 'demo off, no password: /alerts -> 503 (login not configured)');
$authed = ['app' => ['demo' => false], 'auth' => ['enabled' => true, 'password_hash' => password_hash('test-only-password', PASSWORD_DEFAULT)], 'alerts' => ['export_path' => $tmp . '/alerts.json']];
$res = $kernel($authed)->handle(req('GET', '/alerts'));
check($res->status === 401 && !str_contains($res->body, 'alert-list'), 'password set: /alerts needs login');
$res = $kernel(['app' => ['demo' => false], 'auth' => ['enabled' => true, 'password_hash' => password_hash('test-only-password', PASSWORD_DEFAULT)]] + ['alerts' => ['export_path' => $tmp . '/no-alerts.json']])->handle(req('GET', '/alerts'));
check($res->status === 401, 'password set, missing export: still login first');

// ---- Lang files -----------------------------------------------------------------------------------
$enKeys = require $root . '/lang/en/home.php';
$zhKeys = require $root . '/lang/zh-CN/home.php';
check(array_keys($enKeys) === array_keys($zhKeys), 'en and zh-CN home keys match (same order)');
foreach ($enKeys as $key => $text) {
    preg_match_all('/\{\w+\}/', $text, $a);
    preg_match_all('/\{\w+\}/', (string)($zhKeys[$key] ?? ''), $b);
    sort($a[0]);
    sort($b[0]);
    check($a[0] === $b[0], "placeholders match: $key");
}
$code = '';
foreach (array_merge(glob($root . '/src/Home/*.php'), glob($root . '/src/Alerts/*.php'), glob($root . '/src/Controllers/Home/*.php'), glob($root . '/templates/home/*.php'), glob($root . '/templates/home/partials/*.php'), [$root . '/src/Nav/home.php']) as $file) {
    $code .= file_get_contents($file);
}
preg_match_all("/'home\.([a-z0-9_.]+)'/", $code, $used);
foreach (array_unique($used[1]) as $key) {
    // Nav keys (home.overview) and config keys (home.thresholds) have no dot; lang keys do.
    if (str_ends_with($key, '.') || !str_contains($key, '.')) {
        continue;
    }
    check(isset($enKeys[$key]), "lang key exists: home.$key");
}

// ---- Scrub ------------------------------------------------------------------------------------------
check(preg_match('/tr\x61deflash|hiq\x6aj|sch\x77ab|\be\x68g\b/i', $code . file_get_contents($root . '/app/demo.php') . json_encode($enKeys + $zhKeys, JSON_UNESCAPED_UNICODE)) !== 1, 'no private names in home files');

// ---- Done -------------------------------------------------------------------------------------------
$errors = is_file($tmp . '/php-errors.log') ? trim((string)file_get_contents($tmp . '/php-errors.log')) : '';
check($errors === '', 'no PHP errors logged' . ($errors !== '' ? ":\n" . $errors : ''));
array_map('unlink', array_filter(glob($tmp . '/{,sessions/}*', GLOB_BRACE) ?: [], 'is_file'));
@rmdir($tmp . '/sessions');
@rmdir($tmp);

ob_end_clean();
foreach ($log as $line) {
    fwrite(STDERR, $line . "\n");
}
fwrite(STDOUT, sprintf("test-home: %d passed, %d failed\n", $passes, $failures));
exit($failures === 0 ? 0 : 1);
