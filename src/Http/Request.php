<?php
declare(strict_types=1);

namespace SignalOps\Status\Http;

/** Immutable view of the incoming request. Build one with fromGlobals(), or directly in tests. */
final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $cookies = [],
        public readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (is_string($value)) {
                $cookies[(string)$name] = $value;
            }
        }

        return new self(
            strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            self::normalizePath(is_string($path) ? $path : '/'),
            $_GET,
            $_POST,
            $cookies,
            $_SERVER,
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim(rawurldecode($path), '/');
        $path = preg_replace('#/+#', '/', $path) ?? '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    public function post(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;
        return is_scalar($value) ? (string)$value : $default;
    }

    public function cookie(string $key): ?string
    {
        return $this->cookies[$key] ?? null;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string)($this->server[$key] ?? '');
    }

    public function isHttps(): bool
    {
        $https = (string)($this->server['HTTPS'] ?? '');
        return ($https !== '' && $https !== 'off')
            || (string)($this->server['SERVER_PORT'] ?? '') === '443'
            || strtolower($this->header('X-Forwarded-Proto')) === 'https'
            || str_contains(strtolower($this->header('CF-Visitor')), '"scheme":"https"');
    }

    /** Current path plus query string, with the given query keys removed. */
    public function uriWithout(string ...$keys): string
    {
        $query = $this->query;
        foreach ($keys as $key) {
            unset($query[$key]);
        }
        $qs = http_build_query($query);
        return $this->path . ($qs !== '' ? '?' . $qs : '');
    }

    /** Current path plus query string with the given keys set. */
    public function uriWith(array $set): string
    {
        $query = array_replace($this->query, $set);
        $qs = http_build_query($query);
        return $this->path . ($qs !== '' ? '?' . $qs : '');
    }
}
