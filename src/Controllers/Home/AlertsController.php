<?php
declare(strict_types=1);

namespace SignalOps\Status\Controllers\Home;

use SignalOps\Status\Alerts\AlertState;
use SignalOps\Status\Controllers\Controller;
use SignalOps\Status\Http\Response;

/**
 * /alerts — open problems and recent recoveries from the health checker's export
 * (format: src/Alerts/AlertState.php). Login required, except in demo mode where sample data is
 * shown (the Kernel opens login-gated routes in demo mode when no password is configured).
 */
final class AlertsController extends Controller
{
    private const FILTERS = ['all', 'recovered', 'event'];

    public function index(array $params = []): Response
    {
        $now = time();
        $staleAfter = max(60, (int)$this->app->config->get('alerts.stale_after_seconds', 300));
        $demo = $this->app->demo();
        $state = $demo
            ? AlertState::demo($now, $staleAfter)
            : AlertState::load((string)($this->app->config->get('alerts.export_path') ?: AlertState::DEFAULT_PATH), $now, $staleAfter);

        $filter = $this->request->query('filter', 'all');
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }
        $counts = [];
        foreach (self::FILTERS as $f) {
            $counts[$f] = count(array_filter($state['recent'], static fn (array $a): bool => $f === 'all' || $a['kind'] === $f));
        }
        $recent = array_values(array_filter($state['recent'], static fn (array $a): bool => $filter === 'all' || $a['kind'] === $filter));
        $recovered24h = count(array_filter($state['recent'], static fn (array $a): bool => $a['kind'] === 'recovered' && $a['at'] !== null && $now - $a['at'] <= 86400));

        $response = $this->page('home/alerts', [
            'state' => $state,
            'now' => $now,
            'demo' => $demo,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'counts' => $counts,
            'recent' => $recent,
            'recovered24h' => $recovered24h,
        ], [
            'title' => $this->t('home.alerts.title'),
            'subtitle' => $this->t('home.alerts.subtitle'),
        ]);
        return $response->withHeader('Cache-Control', 'private, no-cache');
    }
}
