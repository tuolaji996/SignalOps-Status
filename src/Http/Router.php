<?php
declare(strict_types=1);

namespace SignalOps\Status\Http;

/**
 * Small path router. Routes come from src/Routes/<area>.php, one file per area,
 * each returning a list of route arrays:
 *
 *   ['path' => '/servers/{key}', 'handler' => [ServerController::class, 'show'],
 *    'auth' => false, 'nav' => 'home.servers', 'methods' => ['GET', 'HEAD']]
 *
 * '{name}' matches one path segment ([^/]+). Paths are matched exactly (no trailing slash).
 */
final class Router
{
    /** @var list<array<string, mixed>> */
    private array $routes = [];

    /** @param list<array<string, mixed>> $routes */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->add($route);
        }
    }

    /** Load every src/Routes/*.php file, sorted by name. */
    public static function fromDirectory(string $dir): self
    {
        $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
        sort($files);
        $router = new self();
        foreach ($files as $file) {
            $routes = (static fn (string $__file): mixed => require $__file)($file);
            if (!is_array($routes)) {
                throw new \RuntimeException('Route file must return a list: ' . basename($file));
            }
            foreach ($routes as $route) {
                $route['source'] = basename($file);
                $router->add($route);
            }
        }
        return $router;
    }

    /** @param array<string, mixed> $route */
    public function add(array $route): void
    {
        $path = (string)($route['path'] ?? '');
        if ($path === '' || $path[0] !== '/' || !isset($route['handler'])) {
            throw new \InvalidArgumentException('Route needs a path starting with / and a handler.');
        }
        foreach ($this->routes as $existing) {
            if ($existing['path'] === $path) {
                throw new \LogicException(sprintf(
                    'Duplicate route %s (%s and %s)',
                    $path,
                    (string)($existing['source'] ?? '?'),
                    (string)($route['source'] ?? '?')
                ));
            }
        }
        $route['methods'] = array_map('strtoupper', (array)($route['methods'] ?? ['GET', 'HEAD']));
        $route['auth'] = (bool)($route['auth'] ?? false);
        $route['regex'] = '#^' . preg_replace_callback(
            '/\\\\\{([a-z_][a-z0-9_]*)\\\\\}/i',
            static fn (array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($path, '#')
        ) . '$#';
        $this->routes[] = $route;
    }

    /**
     * @return array{route: array<string, mixed>, params: array<string, string>, method_allowed: bool}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            // Login-required pages also accept POST: the inline login form posts back to the same URL.
            $methods = $route['auth'] ? array_merge($route['methods'], ['POST']) : $route['methods'];
            return ['route' => $route, 'params' => $params, 'method_allowed' => in_array($method, $methods, true)];
        }
        return null;
    }

    /** @return list<array<string, mixed>> */
    public function routes(): array
    {
        return $this->routes;
    }
}
