<?php
declare(strict_types=1);

namespace SignalOps\Status\Auth;

use SignalOps\Status\Http\Request;

/**
 * Operator password login for routes with 'auth' => true.
 *
 * Config (auth.*): enabled, password_hash (from password_hash()), session_name, session_ttl_seconds
 * (sliding). One shared password, a PHP session and a per-session CSRF token; no user accounts.
 * Sessions are only opened when the browser already sent the session cookie, so public pages never
 * create sessions and stay cacheable.
 */
final class Auth
{
    private const SESSION_OK = 'signalops_auth_ok';
    private const SESSION_AT = 'signalops_auth_at';
    private const SESSION_CSRF = 'signalops_csrf';

    /** @var array{enabled: bool, password_hash: string, session_name: string, session_ttl_seconds: int} */
    private array $settings;

    /**
     * @param array<string, mixed> $config Full status config.
     * @param Request|null $request Current request; its session cookie is used when the session is started.
     */
    public function __construct(array $config, private readonly ?Request $request = null)
    {
        $this->settings = self::settings($config);
    }

    /**
     * Normalised auth.* settings.
     * @param array<string, mixed> $config
     * @return array{enabled: bool, password_hash: string, session_name: string, session_ttl_seconds: int}
     */
    public static function settings(array $config): array
    {
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($auth['session_name'] ?? '')) ?: 'signalops_session';
        if (ctype_digit($name)) {
            $name = 'signalops_session';
        }
        return [
            'enabled' => filter_var($auth['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'password_hash' => (string)($auth['password_hash'] ?? ''),
            'session_name' => $name,
            'session_ttl_seconds' => max(300, min(86400, (int)($auth['session_ttl_seconds'] ?? 3600))),
        ];
    }

    public function configured(): bool
    {
        return $this->settings['enabled'] && $this->settings['password_hash'] !== '';
    }

    public function sessionName(): string
    {
        return $this->settings['session_name'];
    }

    /** Start (or resume) the session. */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($this->sessionName());
        $cookie = $this->request?->cookie($this->sessionName());
        if ($cookie !== null && preg_match('/^[A-Za-z0-9,-]{16,256}$/', $cookie)) {
            session_id($cookie);
        } elseif ($this->request !== null) {
            // A Request without a (valid) session cookie: never reuse an id from an earlier session.
            session_id(session_create_id() ?: bin2hex(random_bytes(16)));
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->request?->isHttps() ?? false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        @session_start();
    }

    /**
     * Is the visitor logged in? Only opens the session when the browser already sent the session
     * cookie, so public pages never create sessions (and stay CDN-cacheable).
     */
    public function check(Request $request): bool
    {
        if (!$this->configured()) {
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if ($request->cookie($this->sessionName()) === null) {
                return false;
            }
            $this->start();
        }
        if (empty($_SESSION[self::SESSION_OK]) || empty($_SESSION[self::SESSION_AT])) {
            return false;
        }
        if (time() - (int)$_SESSION[self::SESSION_AT] > $this->settings['session_ttl_seconds']) {
            unset($_SESSION[self::SESSION_OK], $_SESSION[self::SESSION_AT]);
            return false;
        }
        $_SESSION[self::SESSION_AT] = time();
        return true;
    }

    /**
     * Handle the inline login form (POST password + csrf). Returns null on success (caller
     * redirects back to the same URL), or an error code: 'not_configured', 'csrf', 'invalid'.
     */
    public function attemptLogin(Request $request): ?string
    {
        if (!$this->configured()) {
            return 'not_configured';
        }
        $this->start();
        if (!$this->verifyCsrf($request->post('csrf'))) {
            return 'csrf';
        }
        $password = $request->post('password');
        if ($password === '' || !password_verify($password, $this->settings['password_hash'])) {
            return 'invalid';
        }
        @session_regenerate_id(true);
        $_SESSION[self::SESSION_OK] = true;
        $_SESSION[self::SESSION_AT] = time();
        unset($_SESSION[self::SESSION_CSRF]);
        return null;
    }

    public function logout(): void
    {
        $this->start();
        $_SESSION = [];
        if (ini_get('session.use_cookies') && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)$params['secure'],
                'httponly' => (bool)$params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        @session_destroy();
    }

    /** Per-session CSRF token for forms on routed pages. */
    public function csrfToken(): string
    {
        $this->start();
        if (empty($_SESSION[self::SESSION_CSRF]) || !is_string($_SESSION[self::SESSION_CSRF])) {
            $_SESSION[self::SESSION_CSRF] = bin2hex(random_bytes(24));
        }
        return $_SESSION[self::SESSION_CSRF];
    }

    public function verifyCsrf(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $expected = $_SESSION[self::SESSION_CSRF] ?? '';
        return is_string($expected) && $expected !== '' && $token !== '' && hash_equals($expected, $token);
    }

    /**
     * Release the session lock. Call before any slow read so parallel requests from the same
     * browser are not serialised. $_SESSION stays readable afterwards.
     */
    public function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
