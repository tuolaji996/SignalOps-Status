<?php
declare(strict_types=1);

/*
 * Core skeleton tests: request normalisation, router, preference cookies + redirect, i18n fallback,
 * safeReturn, login gate (with a throwaway password hash), Assets tag output.
 *
 *   php scripts/test-core.php      (exits non-zero on failure)
 */

namespace {
ob_start();
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/src/autoload.php';
}

namespace SignalOps\Status\Tests {

use SignalOps\Status\Controllers\Controller;
use SignalOps\Status\Http\Response;

final class ProbeController extends Controller
{
    public function show(array $params = []): Response
    {
        return $this->page('shared/placeholder', ['legacy' => null], ['title' => 'Probe ' . ($params['id'] ?? '')]);
    }

    public function boom(array $params = []): Response
    {
        throw new \RuntimeException('expected test failure');
    }
}

}

namespace {

use SignalOps\Status\Auth\Auth;
use SignalOps\Status\Config\Config;
use SignalOps\Status\Controllers\Shared\SessionController;
use SignalOps\Status\Http\Request;
use SignalOps\Status\Http\Response;
use SignalOps\Status\Http\Router;
use SignalOps\Status\I18n\I18n;
use SignalOps\Status\Kernel;
use SignalOps\Status\Tests\ProbeController;
use SignalOps\Status\View\Assets;
use SignalOps\Status\View\Preferences;

error_reporting(E_ALL);
$root = dirname(__DIR__);

$tmp = rtrim(sys_get_temp_dir(), '/') . '/signalops-test-core-' . getmypid() . '-' . bin2hex(random_bytes(3));
mkdir($tmp . '/sessions', 0700, true);
ini_set('session.save_path', $tmp . '/sessions');
ini_set('session.use_cookies', '0');
ini_set('error_log', $tmp . '/php-errors.log');
ini_set('session.cache_limiter', '');
ini_set('log_errors', '0');

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

function req(string $method, string $uri, array $post = [], array $cookies = [], array $headers = []): Request
{
    $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '/');
    parse_str((string)(parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];
    foreach ($headers as $name => $value) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
    return new Request($method, Request::normalizePath($path), $query, $post, $cookies, $server);
}

function closeSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $_SESSION = [];
}

// ---- Request normalisation ---------------------------------------------------------------------
check(Request::normalizePath('') === '/', 'empty path -> /');
check(Request::normalizePath('/') === '/', '/ stays /');
check(Request::normalizePath('//overview//') === '/overview', 'duplicate and trailing slashes removed');
check(Request::normalizePath('overview') === '/overview', 'leading slash added');
check(Request::normalizePath('/a%20b/') === '/a b', 'percent-decoded');
$r = req('GET', '/servers?x=1&lang=en&theme=dark');
check($r->uriWithout('lang', 'theme') === '/servers?x=1', 'uriWithout drops keys');
check($r->uriWith(['lang' => 'zh-CN']) === '/servers?x=1&lang=zh-CN&theme=dark', 'uriWith sets keys');
check(req('GET', '/', [], [], ['X-Forwarded-Proto' => 'https'])->isHttps(), 'isHttps via X-Forwarded-Proto');
check(!req('GET', '/')->isHttps(), 'plain http');

// ---- Router -------------------------------------------------------------------------------------
$routes = [
    ['path' => '/probe', 'handler' => [ProbeController::class, 'show'], 'nav' => 'home.overview'],
    ['path' => '/probe/{id}', 'handler' => [ProbeController::class, 'show']],
    ['path' => '/secret', 'handler' => [ProbeController::class, 'show'], 'nav' => 'home.alerts', 'auth' => true],
    ['path' => '/boom', 'handler' => [ProbeController::class, 'boom']],
    ['path' => '/logout', 'handler' => [SessionController::class, 'logout'], 'methods' => ['POST']],
];
$router = new Router($routes);
$m = $router->match('GET', '/probe/abc');
check($m !== null && $m['params'] === ['id' => 'abc'] && $m['method_allowed'], 'param route matches');
check($router->match('GET', '/probe/a/b') === null, '{id} matches one segment only');
check($router->match('GET', '/nope') === null, 'unknown path -> null');
check($router->match('POST', '/probe')['method_allowed'] === false, 'POST not allowed by default');
check($router->match('HEAD', '/probe')['method_allowed'] === true, 'HEAD allowed by default');
check($router->match('POST', '/secret')['method_allowed'] === true, 'auth routes accept POST (login form)');
try {
    $router->add(['path' => '/probe', 'handler' => [ProbeController::class, 'show']]);
    check(false, 'duplicate route throws');
} catch (\LogicException) {
    check(true, 'duplicate route throws');
}
try {
    $router->add(['path' => 'relative', 'handler' => [ProbeController::class, 'show']]);
    check(false, 'relative path throws');
} catch (\InvalidArgumentException) {
    check(true, 'relative path throws');
}
$core = require $root . '/src/Routes/core.php';
check(is_array($core) && $core[0]['path'] === '/logout', 'core routes load');
$homeNav = require $root . '/src/Nav/home.php';
check(array_column($homeNav['items'], 'href') === ['/overview', '/servers', '/latency', '/logs', '/events', '/alerts'], 'home nav items');

// ---- Kernel helpers ----------------------------------------------------------------------------
$demoConfig = Config::fromArray(['app' => ['demo' => true]]);
$kernel = static fn (Config $config): Kernel => new Kernel($root, $config, new Router($routes));

// ---- Preferences: cookies + redirect -----------------------------------------------------------
$res = $kernel($demoConfig)->handle(req('GET', '/probe?lang=zh-CN&theme=dark&x=1'));
$cookies = [];
foreach ($res->cookies as $c) {
    $cookies[$c['name']] = $c['value'];
}
check($res->status === 303, 'explicit lang/theme -> 303');
check(($res->headers['Location'] ?? '') === '/probe?x=1', 'redirect drops lang/theme, keeps other query');
check(($cookies[Preferences::LANG_COOKIE] ?? null) === 'zh-CN' && ($cookies[Preferences::THEME_COOKIE] ?? null) === 'dark', 'lang and theme cookies set');
check(Preferences::LANG_COOKIE === 'signalops_lang' && Preferences::THEME_COOKIE === 'signalops_theme', 'cookie names');
check(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'preference redirect is no-store');
$res = $kernel($demoConfig)->handle(req('GET', '/probe?theme=dark'));
check($res->status === 303 && count($res->cookies) === 1 && $res->cookies[0]['name'] === Preferences::THEME_COOKIE, 'theme only -> only theme cookie');
$res = $kernel($demoConfig)->handle(req('GET', '/probe?lang=fr'));
check($res->status === 200, 'unsupported lang does not redirect');
$p = Preferences::fromRequest(req('GET', '/', [], [], ['Accept-Language' => 'fr;q=0.9, zh-TW;q=0.8, en;q=0.5']));
check($p->locale === 'zh-CN' && $p->theme === 'system' && !$p->explicitChange, 'Accept-Language zh-* -> zh-CN, theme system');
$p = Preferences::fromRequest(req('GET', '/', [], [Preferences::LANG_COOKIE => 'en', Preferences::THEME_COOKIE => 'light'], ['Accept-Language' => 'zh-CN']));
check($p->locale === 'en' && $p->dataTheme() === 'light', 'cookies beat Accept-Language');
check($p->nextTheme() === 'dark' && $p->otherLocale() === 'zh-CN', 'nextTheme / otherLocale');

$res = $kernel($demoConfig)->handle(req('GET', '/probe', [], [Preferences::LANG_COOKIE => 'zh-CN', Preferences::THEME_COOKIE => 'dark']));
check($res->status === 200, 'page renders');
check(str_contains($res->body, '<html lang="zh-CN" data-theme="dark"'), 'html lang + data-theme from cookies');
check(str_contains($res->body, 'data-theme-cookie="signalops_theme"') && str_contains($res->body, 'data-timezone="'), 'html carries theme cookie name and timezone');
check(($res->headers['Content-Language'] ?? '') === 'zh-CN' && ($res->headers['X-Content-Type-Options'] ?? '') === 'nosniff', 'security + language headers');
check(str_contains($res->body, '/assets/site/core/core.css?v=') && str_contains($res->body, '<script type="module" src="/assets/site/core/core.js?v='), 'layout links core assets');
check(str_contains($res->body, 'href="/alerts"') && str_contains($res->body, 'class="ui-lock"'), 'sidebar shows nav with lock icon');

$res = $kernel($demoConfig)->handle(req('GET', '/missing'));
check($res->status === 404 && str_contains($res->body, 'Page not found'), '404 page');
$res = $kernel($demoConfig)->handle(req('GET', '/missing', [], [Preferences::LANG_COOKIE => 'zh-CN']));
check($res->status === 404 && str_contains($res->body, '找不到页面'), '404 page in zh-CN');
$res = $kernel($demoConfig)->handle(req('POST', '/probe'));
check($res->status === 405 && ($res->headers['Allow'] ?? '') === 'GET, HEAD', '405 with Allow');
$res = $kernel($demoConfig)->handle(req('GET', '/boom'));
check($res->status === 500 && str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'controller exception -> 500');

// ---- I18n fallback ------------------------------------------------------------------------------
mkdir($tmp . '/lang/en', 0700, true);
mkdir($tmp . '/lang/zh-CN', 0700, true);
file_put_contents($tmp . '/lang/en/demo.php', "<?php return ['hello' => 'Hello {name}', 'only_en' => 'English only'];");
file_put_contents($tmp . '/lang/zh-CN/demo.php', "<?php return ['hello' => '你好 {name}'];");
$zh = new I18n('zh-CN', $tmp . '/lang', 'UTC');
check($zh->t('demo.hello', ['name' => 'Ada']) === '你好 Ada', 'zh-CN string with placeholder');
check($zh->t('demo.only_en') === 'English only', 'missing zh-CN key falls back to en');
check($zh->t('demo.nope') === 'demo.nope', 'missing everywhere -> key');
check(!$zh->has('demo.only_en') && $zh->has('demo.hello'), 'has() checks the active locale only');
check($zh->pick(['en' => 'A', 'zh-CN' => 'B']) === 'B' && $zh->pick(['en' => 'A']) === 'A', 'pick() with fallback');
check($zh->datetime(0, 'full') === '1970-01-01 00:00:00 UTC' && $zh->zoneLabel() === 'UTC', 'configured timezone');
check((new I18n('en', $tmp . '/lang', 'Not/AZone'))->timezone === I18n::TIMEZONE, 'invalid timezone -> default');
check(I18n::negotiate(null, null, '') === 'en' && I18n::negotiate('zh', null, '') === 'zh-CN', 'negotiate');
$en = new I18n('en', $root . '/lang');
$zhReal = new I18n('zh-CN', $root . '/lang');
check($en->relative(time() - 300) === '5 min ago' && $zhReal->relative(time() - 300) === '5 分钟前', 'relative time in both languages');
$enKeys = require $root . '/lang/en/common.php';
$zhKeys = require $root . '/lang/zh-CN/common.php';
check(array_keys($enKeys) === array_keys($zhKeys), 'common.php keys identical in en and zh-CN');
$placeholders = static function (array $strings): array {
    $out = [];
    foreach ($strings as $k => $text) {
        preg_match_all('/\{[a-z_]+\}/', (string)$text, $m);
        sort($m[0]);
        $out[$k] = $m[0];
    }
    return $out;
};
check($placeholders($enKeys) === $placeholders($zhKeys), 'common.php placeholders identical');

// ---- safeReturn ---------------------------------------------------------------------------------
check(Kernel::safeReturn('/alerts?x=1', '/') === '/alerts?x=1', 'safeReturn keeps local path');
foreach (['', 'https://evil.example', '//evil.example', '/\\evil', "/a\nb", 'alerts', 'javascript:alert(1)'] as $bad) {
    check(Kernel::safeReturn($bad, '/') === '/', 'safeReturn rejects ' . json_encode($bad));
}

// ---- Login gate ---------------------------------------------------------------------------------
$password = bin2hex(random_bytes(8));
$authConfig = Config::fromArray([
    'app' => ['demo' => false],
    'auth' => ['enabled' => true, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'session_name' => 'so_test_session'],
]);
check((new Auth($authConfig->all()))->configured(), 'auth configured with hash');
check(!(new Auth(Config::fromArray()->all()))->configured(), 'auth not configured by default');
check(Auth::settings(['auth' => ['session_name' => 'bad name!']])['session_name'] === 'badname', 'session name sanitised');

// Not configured: demo mode opens the page, non-demo shows 503.
closeSession();
$res = $kernel($demoConfig)->handle(req('GET', '/secret'));
check($res->status === 200 && str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'demo without password: gated page open but private');
closeSession();
$res = $kernel(Config::fromArray(['app' => ['demo' => false]]))->handle(req('GET', '/secret'));
check($res->status === 503 && str_contains($res->body, 'Login is not configured'), 'non-demo without password: 503 not configured');

// Configured: public page creates no session.
closeSession();
$res = $kernel($authConfig)->handle(req('GET', '/probe'));
check($res->status === 200 && session_status() !== PHP_SESSION_ACTIVE && !str_contains($res->body, 'action="/logout"'), 'public page: no session, no logout button');

closeSession();
$res = $kernel($authConfig)->handle(req('GET', '/secret'));
$sid = session_id();
preg_match('/name="csrf" value="([0-9a-f]+)"/', $res->body, $mm);
$csrf = $mm[1] ?? '';
check($res->status === 401 && str_contains($res->body, 'id="login-password"') && $csrf !== '' && $sid !== '', 'gated page without session -> 401 login form with CSRF');
check(str_contains($res->headers['Cache-Control'] ?? '', 'no-store') && ($res->headers['X-Robots-Tag'] ?? '') === 'noindex, nofollow', 'login page is private');
$cookie = ['so_test_session' => $sid];

closeSession();
$res = $kernel($authConfig)->handle(req('POST', '/secret', ['form' => 'login', 'csrf' => 'wrong', 'password' => $password], $cookie));
check($res->status === 401 && str_contains($res->body, 'The form expired'), 'bad CSRF rejected');

closeSession();
$res = $kernel($authConfig)->handle(req('POST', '/secret', ['form' => 'login', 'csrf' => $csrf, 'password' => 'not-it'], $cookie));
check($res->status === 401 && str_contains($res->body, 'Wrong password'), 'wrong password rejected');
preg_match('/name="csrf" value="([0-9a-f]+)"/', $res->body, $mm);
$csrf = $mm[1] ?? $csrf;

closeSession();
$res = $kernel($authConfig)->handle(req('POST', '/secret?tab=1', ['form' => 'login', 'csrf' => $csrf, 'password' => $password], $cookie));
$newSid = session_id();
check($res->status === 303 && ($res->headers['Location'] ?? '') === '/secret?tab=1', 'correct password -> 303 back to same URL');
check($newSid !== '' && $newSid !== $sid, 'session id regenerated on login');
$cookie = ['so_test_session' => $newSid];

closeSession();
$res = $kernel($authConfig)->handle(req('GET', '/secret', [], $cookie));
check($res->status === 200 && str_contains($res->body, 'Probe ') && str_contains($res->body, 'action="/logout"'), 'logged in: gated page renders with logout');
check(str_contains($res->headers['Cache-Control'] ?? '', 'no-store'), 'gated page is private');
preg_match('/<form class="topbar-logout"[\s\S]*?name="csrf" value="([0-9a-f]+)"/', $res->body, $mm);
$logoutCsrf = $mm[1] ?? '';

closeSession();
$res = $kernel($authConfig)->handle(req('POST', '/logout', ['csrf' => 'wrong', 'return' => '/secret'], $cookie));
closeSession();
$still = $kernel($authConfig)->handle(req('GET', '/secret', [], $cookie));
check($res->status === 303 && $still->status === 200, 'logout with bad CSRF does nothing');

closeSession();
$res = $kernel($authConfig)->handle(req('POST', '/logout', ['csrf' => $logoutCsrf, 'return' => '//evil.example'], $cookie));
check($res->status === 303 && ($res->headers['Location'] ?? '') === '/', 'logout redirects to a safe target');
closeSession();
$res = $kernel($authConfig)->handle(req('GET', '/secret', [], $cookie));
check($res->status === 401, 'after logout: login required again');
closeSession();

// ---- Assets -------------------------------------------------------------------------------------
mkdir($tmp . '/site/core', 0700, true);
mkdir($tmp . '/site/home', 0700, true);
file_put_contents($tmp . '/site/core/core.css', 'body{}');
file_put_contents($tmp . '/site/core/core.js', '');
file_put_contents($tmp . '/site/home/home.css', '');
touch($tmp . '/site/core/core.css', 1700000000);
$assets = new Assets($tmp . '/site', '/assets/site/');
$tags = $assets->tags(['core', 'home', 'evil', 'core']);
$lines = explode("\n", $tags);
check($lines[0] === '<link rel="stylesheet" href="/assets/site/core/core.css?v=1700000000">', 'core css tag with mtime');
check(str_contains($lines[1], '/assets/site/home/home.css?v='), 'area css after core css');
check(count($lines) === 3 && str_starts_with($lines[2], '<script type="module" src="/assets/site/core/core.js?v='), 'scripts after styles; missing home.js skipped; unknown/duplicate entries ignored');
check(!str_contains($tags, 'evil'), 'unknown entry not emitted');
@unlink($tmp . '/site/home/home.css');
check(str_contains($assets->tags(['home']), '<!-- asset entry "home" is missing'), 'missing entry leaves a comment');
check($assets->url('core', 'nope.css') === '/assets/site/core/nope.css', 'url() without file has no version');
check(Assets::ENTRIES === ['core', 'home'], 'entries are core + home');

// ---- Config -------------------------------------------------------------------------------------
file_put_contents($tmp . '/c1.php', "<?php \$config = 'clobbered'; return ['alerts' => ['stale_after_seconds' => 120], 'refresh_seconds' => 1];");
$cfg = Config::load($root, [$tmp . '/c1.php', $tmp . '/missing.php']);
check($cfg->get('alerts.stale_after_seconds') === 120 && $cfg->get('alerts.export_path') === '/var/lib/signalops-status/alerts/page.json', 'config file merges over DEFAULTS');
check($cfg->get('refresh_seconds') === 10 && $cfg->get('ui.cache_dir') === '' && $cfg->demo(), 'refresh clamp, ui default, demo default true');
check($cfg->get('app.name') === 'SignalOps Status', 'default_signalops_config() included');
check($cfg->get('no.such.key', 'd') === 'd', 'missing key default');
check(Config::merge(['a' => [1, 2], 'b' => ['x' => 1]], ['a' => [3], 'b' => ['y' => 2]]) === ['a' => [3], 'b' => ['x' => 1, 'y' => 2]], 'merge: lists replace, maps merge');

// ---- Cleanup + report ---------------------------------------------------------------------------
$rm = static function (string $dir) use (&$rm): void {
    foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
        if (in_array(basename($f), ['.', '..'], true)) {
            continue;
        }
        is_dir($f) ? $rm($f) : @unlink($f);
    }
    @rmdir($dir);
};
$rm($tmp);

ob_end_clean();
foreach ($log as $line) {
    fwrite(STDERR, $line . "\n");
}
fwrite(STDOUT, sprintf("test-core: %d passed, %d failed\n", $passes, $failures));
exit($failures === 0 ? 0 : 1);

}
