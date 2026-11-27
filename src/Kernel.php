<?php
declare(strict_types=1);

namespace SignalOps\Status;

use SignalOps\Status\Config\Config;
use SignalOps\Status\Http\Request;
use SignalOps\Status\Http\Response;
use SignalOps\Status\Http\Router;
use SignalOps\Status\View\Navigation;
use SignalOps\Status\View\Preferences;

/**
 * Request pipeline for routed pages: preferences -> route -> login -> controller.
 * Entry: src/front.php (from public/index.php for every path except /index.php and /?classic=1).
 */
final class Kernel
{
    private ?Navigation $nav = null;

    /** $router: inject a router in tests; by default every src/Routes/*.php file is loaded. */
    public function __construct(private readonly string $root, private readonly Config $config, private ?Router $router = null)
    {
    }

    public static function boot(string $root): self
    {
        return new self($root, Config::load($root));
    }

    public function router(): Router
    {
        return $this->router ??= Router::fromDirectory($this->root . '/src/Routes');
    }

    public function handle(Request $request): Response
    {
        $prefs = Preferences::fromRequest($request);
        if ($prefs->explicitChange && in_array($request->method, ['GET', 'HEAD'], true)) {
            $response = Response::redirect($request->uriWithout('lang', 'theme'), 303);
            $options = Preferences::cookieOptions($request);
            if ($request->query('lang') !== '') {
                $response->withCookie(Preferences::LANG_COOKIE, $prefs->locale, $options);
            }
            if (in_array($request->query('theme'), Preferences::THEMES, true)) {
                $response->withCookie(Preferences::THEME_COOKIE, $prefs->theme, $options);
            }
            return $response->withHeader('Cache-Control', 'no-store');
        }

        $this->nav ??= Navigation::fromDirectory($this->root . '/src/Nav');
        $app = new App($this->root, $this->config, $request, $prefs, $this->nav);
        $response = $this->dispatch($app, $request);
        $response->headers += [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Vary' => 'Cookie, Accept-Language',
        ];
        $response->headers['Content-Language'] = $app->i18n->htmlLang();
        $response->headers['Server-Timing'] ??= sprintf('app;dur=%.1f', \SignalOps\Status\Http\RequestStats::elapsed() * 1000);
        return $response;
    }

    private function dispatch(App $app, Request $request): Response
    {
        $match = $this->router()->match($request->method, $request->path);
        if ($match === null) {
            return $this->error($app, 404);
        }
        if (!$match['method_allowed']) {
            return $this->error($app, 405)->withHeader('Allow', implode(', ', $match['route']['methods']));
        }
        $route = $match['route'];

        if ($route['auth']) {
            $gate = $this->gate($app, $request, $route);
            if ($gate !== null) {
                return $gate;
            }
        } else {
            $app->authenticated = $app->auth->check($request);
            if ($app->authenticated) {
                $app->csrf = $app->auth->csrfToken();
            }
            $app->auth->release();
        }

        [$class, $method] = $route['handler'];
        try {
            $controller = new $class($app, $request, $route);
            $response = $controller->$method($match['params']);
        } catch (\Throwable $error) {
            error_log('[signalops-status] ' . $request->path . ': ' . $error::class . ': ' . $error->getMessage() . ' @ ' . $error->getFile() . ':' . $error->getLine());
            return $this->error($app, 500, $route);
        }
        if (!$response instanceof Response) {
            return $this->error($app, 500, $route);
        }
        if ($route['auth']) {
            $response->private();
        }
        return $response;
    }

    /** Login gate for 'auth' routes. Returns a response to send instead of the page, or null to continue. */
    private function gate(App $app, Request $request, array $route): ?Response
    {
        $page = ['nav' => (string)($route['nav'] ?? ''), 'private' => true, 'title' => $app->t('common.login.title'), 'area' => 'home'];
        if (!$app->auth->configured() && $app->config->demo()) {
            // Demo mode without a password: login-gated pages are open so the demo shows every page.
            return null;
        }
        if (!$app->auth->configured()) {
            $page['status_code'] = 503;
            return $app->view->page('shared/login', ['error' => 'not_configured', 'csrf' => ''], $page);
        }

        $error = null;
        if ($request->method === 'POST' && $request->post('form') === 'login') {
            $error = $app->auth->attemptLogin($request);
            if ($error === null) {
                return Response::redirect($request->uriWithout(), 303)->private();
            }
        } elseif ($app->auth->check($request)) {
            $app->authenticated = true;
            $app->csrf = $app->auth->csrfToken();
            // Release the session lock before the controller does any reading.
            $app->auth->release();
            return null;
        }

        $csrf = $app->auth->csrfToken();
        $app->auth->release();
        $page['status_code'] = 401;
        return $app->view->page('shared/login', ['error' => $error, 'csrf' => $csrf], $page);
    }

    private function error(App $app, int $code, array $route = []): Response
    {
        $page = ['title' => $app->t('common.error.' . $code . '.title'), 'status_code' => $code, 'private' => true, 'nav' => (string)($route['nav'] ?? '')];
        try {
            return $app->view->page('shared/error', ['code' => $code], $page);
        } catch (\Throwable $error) {
            error_log('[signalops-status] error page failed: ' . $error->getMessage());
            return (new Response('Error ' . $code, $code, ['Content-Type' => 'text/plain; charset=utf-8']))->private();
        }
    }

    /** Only allow same-site relative paths as redirect targets. */
    public static function safeReturn(string $target, string $default): string
    {
        if ($target === '' || $target[0] !== '/' || str_starts_with($target, '//') || str_contains($target, '\\') || preg_match('/[\x00-\x1f]/', $target)) {
            return $default;
        }
        return $target;
    }
}
