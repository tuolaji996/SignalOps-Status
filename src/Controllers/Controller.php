<?php
declare(strict_types=1);

namespace SignalOps\Status\Controllers;

use SignalOps\Status\App;
use SignalOps\Status\Http\Request;
use SignalOps\Status\Http\Response;

/**
 * Base for page controllers. A route handler [FooController::class, 'show'] is called as
 * (new FooController($app, $request, $route))->show($params).
 *
 * Rules: read caches/snapshots only (app/*.php readers, Cache, JsonFile); never run slow DB or
 * network work inside a request. Login has already been checked when the route has 'auth' => true.
 */
abstract class Controller
{
    /** Areas that have their own asset entry and nav group. Only 'home' ships in the open-source build. */
    public const AREAS = ['home'];

    /** @param array<string, mixed> $route */
    public function __construct(protected readonly App $app, protected readonly Request $request, protected readonly array $route)
    {
    }

    /**
     * Render a page in the shared layout. 'nav' and 'private' default from the route,
     * 'title' defaults to the nav item's label.
     */
    protected function page(string $template, array $data = [], array $page = []): Response
    {
        $nav = (string)($page['nav'] ?? $this->route['nav'] ?? '');
        if (!isset($page['title'])) {
            $item = $nav !== '' ? $this->app->nav->item($nav) : null;
            $page['title'] = $item !== null ? $this->app->t((string)$item['label']) : '';
        }
        $page += [
            'nav' => $nav,
            'private' => (bool)($this->route['auth'] ?? false),
            'area' => $this->area(),
        ];
        return $this->app->view->page($template, $data, $page);
    }

    /** Area of this controller, from its namespace (Controllers\Home\... => 'home'); unknown areas fall back to 'home'. */
    protected function area(): string
    {
        $parts = explode('\\', static::class);
        $area = strtolower($parts[count($parts) - 2] ?? 'home');
        return in_array($area, self::AREAS, true) ? $area : 'home';
    }

    protected function t(string $key, array $params = []): string
    {
        return $this->app->t($key, $params);
    }
}
