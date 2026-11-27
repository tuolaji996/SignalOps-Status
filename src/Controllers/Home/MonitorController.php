<?php
declare(strict_types=1);

namespace SignalOps\Status\Controllers\Home;

use SignalOps\Status\Controllers\Controller;
use SignalOps\Status\Home\HomeData;
use SignalOps\Status\Http\Response;

/**
 * Public MONITOR pages: /overview, /servers, /latency, /logs, /events and the /overview/live JSON
 * the pages poll every 60 s. Everything comes from the status snapshot (HomeData); nothing here
 * probes, queries a database or opens a connection.
 */
final class MonitorController extends Controller
{
    private const REFRESH_SECONDS = 60;

    public function overview(array $params = []): Response
    {
        return $this->render('home/overview', 'overview');
    }

    public function servers(array $params = []): Response
    {
        return $this->render('home/servers', 'servers');
    }

    public function latency(array $params = []): Response
    {
        return $this->render('home/latency', 'latency');
    }

    public function logs(array $params = []): Response
    {
        return $this->render('home/logs', 'logs');
    }

    public function events(array $params = []): Response
    {
        return $this->render('home/events', 'events');
    }

    /** Numbers only, for the in-place refresh (public/assets/site/home). */
    public function live(array $params = []): Response
    {
        ['snap' => $snap, 'model' => $model] = HomeData::get($this->app);
        $response = Response::json([
            'created_at' => $snap['created_at'] !== null ? gmdate('Y-m-d\TH:i:s\Z', (int)$snap['created_at']) : null,
            'cache_state' => $snap['cache_state'],
            'values' => $model['live'],
        ]);
        return $this->headers($response, $snap)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-cache, private');
    }

    private function render(string $template, string $page): Response
    {
        ['snap' => $snap, 'model' => $model] = HomeData::get($this->app);
        $view = $this->request->query('view') === 'map' ? 'map' : 'matrix';
        $response = $this->page($template, [
            'model' => $model,
            'current' => $page,
            'latencyView' => $view,
            'refreshSeconds' => self::REFRESH_SECONDS,
        ], [
            'subtitle' => $this->t('home.' . $page . '.subtitle'),
            'hide_heading' => $page === 'overview',
        ]);
        return $this->headers($response, $snap)->withHeader('Cache-Control', 'private, no-cache');
    }

    /** Same snapshot headers as the classic page (public/index.php). */
    private function headers(Response $response, array $snap): Response
    {
        $state = $snap['state'] === 'ok' ? (!empty($snap['demo']) ? 'demo' : (string)$snap['cache_state']) : 'missing';
        $age = (int)($snap['age'] ?? 0);
        return $response
            ->withHeader('X-SignalOps-Status-Cache', $state)
            ->withHeader('X-SignalOps-Status-Cache-Age', (string)$age)
            ->withHeader('Server-Timing', 'signalops_cache;desc="' . $state . '";dur=' . $age);
    }
}
