<?php
declare(strict_types=1);

namespace SignalOps\Status\Home;

/**
 * The status snapshot: the collect_status() output that scripts/refresh-cache.php (run by
 * deploy/systemd/signalops-status-cache.timer) or the classic page writes to cache.path. The new
 * pages only read it: no probes, DB queries or SSH inside a request. In demo mode (app.demo) the
 * sample data from app/demo.php is used instead and no file is read.
 */
final class StatusSnapshot
{
    /**
     * @param array<string, mixed> $config full config (Config::all())
     * @return array{state: 'ok'|'missing', status: array<string, mixed>, created_at: int|null, age: int|null,
     *               cache_state: 'fresh'|'stale'|'expired'|'missing', demo: bool}
     */
    public static function read(array $config, int $now): array
    {
        if (filter_var($config['app']['demo'] ?? false, FILTER_VALIDATE_BOOLEAN) && function_exists('demo_status')) {
            $status = demo_status($config);
            unset($status['_cache'], $status['_meta']);
            return ['state' => 'ok', 'status' => $status, 'created_at' => $now, 'age' => 0, 'cache_state' => 'fresh', 'demo' => true];
        }

        $cache = is_array($config['cache'] ?? null) ? $config['cache'] : [];
        $path = (string)($cache['path'] ?? '');
        $fresh = max(0, (int)($cache['seconds'] ?? 90));
        $stale = max($fresh, (int)($cache['stale_seconds'] ?? 300));
        $keep = max($stale, (int)($cache['serve_expired_seconds'] ?? 604800));

        $payload = $path !== '' && function_exists('read_status_cache_payload') ? read_status_cache_payload($path, $keep) : null;
        if (!is_array($payload)) {
            return ['state' => 'missing', 'status' => [], 'created_at' => null, 'age' => null, 'cache_state' => 'missing', 'demo' => false];
        }
        $created = (int)$payload['created_at'];
        $age = max(0, $now - $created);
        return [
            'state' => 'ok',
            'status' => (array)$payload['status'],
            'created_at' => $created,
            'age' => $age,
            'cache_state' => $age > $stale ? 'expired' : ($age > $fresh ? 'stale' : 'fresh'),
            'demo' => false,
        ];
    }
}
