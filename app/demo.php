<?php
declare(strict_types=1);

function demo_status(array $config): array
{
    $now = time();
    $machines = annotate_journal_recovery(demo_machines($now), $now);

    return [
        '_cache' => ['state' => 'demo'],
        'database' => [
            'ok' => true,
            'summary' => [
                'event_count' => 128476,
                'latest_event_at' => gmdate('Y-m-d H:i:s', $now - 62),
                'latest_insert_at' => gmdate('Y-m-d H:i:s', $now - 58),
                'duplicate_seen_total' => 1843,
                'symbol_count' => 932,
            ],
            'kinds' => [
                ['kind' => 'options', 'count' => 71240],
                ['kind' => 'equity', 'count' => 36518],
                ['kind' => 'futures', 'count' => 14201],
                ['kind' => 'alerts', 'count' => 6517],
            ],
            'recent' => [
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 62), 'kind' => 'options', 'symbol' => 'NVDA', 'text' => 'Large call sweep detected above current ask.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 148), 'kind' => 'equity', 'symbol' => 'SPY', 'text' => 'High-volume tape print routed to Discord.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 311), 'kind' => 'alerts', 'symbol' => 'TSLA', 'text' => 'Momentum scanner alert acknowledged by bot.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 486), 'kind' => 'futures', 'symbol' => 'ES', 'text' => 'Opening range breakout flagged on the front-month contract.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 702), 'kind' => 'options', 'symbol' => 'AAPL', 'text' => 'Put spread opened near the weekly expiry.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 1015), 'kind' => 'equity', 'symbol' => 'QQQ', 'text' => 'Block trade printed at the midpoint.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 1388), 'kind' => 'alerts', 'symbol' => 'AMD', 'text' => 'Volume spike alert posted to the signals channel.'],
                ['seen_at' => gmdate('Y-m-d H:i:s', $now - 1902), 'kind' => 'options', 'symbol' => 'MSFT', 'text' => 'Unusual open interest change detected.'],
            ],
            'error' => null,
        ],
        'endpoints' => [
            'discord' => [
                'label' => (string)($config['endpoints']['discord']['label'] ?? 'Discord Bot'),
                'ok' => true,
                'http_code' => 200,
                'data' => [
                    'ok' => true,
                    'service' => 'discord-signal-bot',
                    'seen_hashes' => 2000,
                    'poll_count' => 1936,
                    'publish_count' => 14382,
                    'last_poll_ok_at' => gmdate('c', $now - 42),
                    'last_event_at' => gmdate('c', $now - 62),
                    'last_api_capture_at' => gmdate('c', $now - 45),
                ],
                'error' => null,
            ],
            'api' => [
                'label' => (string)($config['endpoints']['api']['label'] ?? 'Trading API'),
                'ok' => true,
                'http_code' => 200,
                'data' => [
                    'ok' => true,
                    'capture_count' => 68421,
                    'change_count' => 4589,
                    'last_capture_at' => gmdate('c', $now - 44),
                ],
                'error' => null,
            ],
        ],
        'machines' => $machines,
        'latency_map' => build_latency_map(demo_latency_map(), $machines),
    ];
}

function demo_machines(int $now): array
{
    $base = [
        ['web', 'Web Server', 'Public dashboard', 'Los Angeles, CA', 7.5, 41.8, 0.0, 2147483648, 110, ['nginx' => 'active']],
        ['db', 'Database Server', 'Long-term archive', 'Chicago, IL', 11.9, 33.2, 0.2, 8589934592, 38, ['mysql' => 'active']],
        ['discord', 'Discord Bot Server', 'Bot runtime', 'Toronto, ON', 4.3, 49.5, 0.0, 2147483648, 64, ['discord-bot.service' => 'active']],
        ['api', 'Trading API Server', 'Market data capture', 'Toronto, ON', 29.2, 58.7, 0.1, 4294967296, 66, ['trading-api.service' => 'active']],
        ['heatmap', 'Heatmap Server', 'Options analytics', 'Toronto, ON', 24.4, 37.0, 0.0, 8589934592, 3, ['heatmap-worker.service' => 'active']],
        ['app', 'Application Server', 'Member portal', 'Montreal, QC', 8.7, 44.2, 0.0, 8589934592, 18, ['nginx' => 'active']],
    ];

    $latencies = [
        'web' => ['db' => 38, 'discord' => 72, 'api' => 74, 'heatmap' => 73, 'app' => 78],
        'db' => ['web' => 39, 'discord' => 12, 'api' => 13, 'heatmap' => 12, 'app' => 18],
        'discord' => ['web' => 71, 'db' => 12, 'api' => 2.2, 'heatmap' => 1.4, 'app' => 8],
        'api' => ['web' => 74, 'db' => 13, 'discord' => 2.1, 'heatmap' => 2.0, 'app' => 8.6],
        'heatmap' => ['web' => 73, 'db' => 12, 'discord' => 1.4, 'api' => 2.0, 'app' => 8.1],
        'app' => ['web' => 78, 'db' => 18, 'discord' => 8, 'api' => 8.5, 'heatmap' => 8.2],
    ];

    $machines = [];
    foreach ($base as $index => $row) {
        [$key, $label, $role, $location, $cpu, $memory, $iowait, $totalMem, $trafficMb, $services] = $row;
        $machineLatencies = [];
        foreach (($latencies[$key] ?? []) as $target => $ms) {
            $machineLatencies[$target] = ['ok' => true, 'latency_ms' => $ms, 'error' => null];
        }

        $machines[] = [
            'key' => $key,
            'label' => $label,
            'role' => $role,
            'location' => $location,
            'probe' => [
                'ok' => true,
                'cpu_pct' => $cpu,
                'iowait_pct' => $iowait,
                'memory' => ['total' => $totalMem, 'available' => $totalMem * (1 - $memory / 100), 'used_pct' => $memory],
                'cpu' => ['model' => $index % 2 === 0 ? 'AMD EPYC virtual CPU' : 'Intel Xeon virtual CPU', 'cores' => $index % 2 === 0 ? 2 : 4],
                'network' => ['iface' => 'private0', 'rx_bytes' => $trafficMb * 1024 * 1024, 'tx_bytes' => ($trafficMb + 18) * 1024 * 1024, 'total_bytes' => ($trafficMb * 2 + 18) * 1024 * 1024],
                'traffic' => ['today_bytes' => $trafficMb * 1024 * 1024, 'month_bytes' => $trafficMb * 1024 * 1024 * 18, 'avg_daily_bytes' => $trafficMb * 1024 * 1024, 'tracked_days' => 18],
                'latencies' => $machineLatencies,
                'disks' => array_merge(
                    [['path' => '/', 'total' => 64 * 1024 ** 3, 'free' => (64 - (12 + $index * 3)) * 1024 ** 3, 'used_pct' => 18.7 + $index * 4.1]],
                    $key === 'db' ? [['path' => '/var/lib/mysql', 'total' => 500 * 1024 ** 3, 'free' => 91 * 1024 ** 3, 'used_pct' => 81.8]] : []
                ),
                'services' => $services,
                'journal' => $key === 'discord'
                    ? demo_journal($now)
                    : ['ok' => null, 'window' => '24h', 'warning_count' => 0, 'error_count' => 0, 'latest_at' => null, 'latest' => []],
                'load' => [0.08 + $index / 10, 0.12 + $index / 12, 0.10 + $index / 15],
                'uptime_seconds' => 86400 * (8 + $index * 3) + 3600 * 5,
                'error' => null,
            ],
            'sla' => [
                'last_ok' => true,
                'last_latency_ms' => $key === 'web' ? 0.2 : (float)(20 + $index * 8),
                'today_pct' => 99.98 - $index * 0.06,
                'month_pct' => 99.94 - $index * 0.04,
                'checks_today' => 338,
                'error' => null,
            ],
        ];
    }

    return $machines;
}

function demo_latency_map(): array
{
    return [
        'enabled' => true,
        'nodes' => [
            'web' => ['label' => 'Web Server', 'location' => 'Los Angeles, CA', 'x' => 15, 'y' => 65],
            'db' => ['label' => 'Database Server', 'location' => 'Chicago, IL', 'x' => 58, 'y' => 45],
            'discord' => ['label' => 'Discord Bot Server', 'location' => 'Toronto, ON', 'x' => 72, 'y' => 62],
            'api' => ['label' => 'Trading API Server', 'location' => 'Toronto, ON', 'x' => 70, 'y' => 28],
            'heatmap' => ['label' => 'Heatmap Server', 'location' => 'Toronto, ON', 'x' => 80, 'y' => 75],
            'app' => ['label' => 'Application Server', 'location' => 'Montreal, QC', 'x' => 88, 'y' => 40],
        ],
        'links' => [
            ['from' => 'web', 'to' => 'db', 'label' => 'Web to Database'],
            ['from' => 'web', 'to' => 'discord', 'label' => 'Web to Discord'],
            ['from' => 'web', 'to' => 'api', 'label' => 'Web to Trading API'],
            ['from' => 'web', 'to' => 'heatmap', 'label' => 'Web to Heatmap'],
            ['from' => 'web', 'to' => 'app', 'label' => 'Web to Application'],
            ['from' => 'db', 'to' => 'discord', 'label' => 'Database to Discord'],
            ['from' => 'db', 'to' => 'api', 'label' => 'Database to Trading API'],
            ['from' => 'db', 'to' => 'heatmap', 'label' => 'Database to Heatmap'],
            ['from' => 'db', 'to' => 'app', 'label' => 'Database to Application'],
            ['from' => 'discord', 'to' => 'api', 'label' => 'Discord to Trading API'],
            ['from' => 'discord', 'to' => 'heatmap', 'label' => 'Discord to Heatmap'],
            ['from' => 'api', 'to' => 'heatmap', 'label' => 'Trading API to Heatmap'],
            ['from' => 'app', 'to' => 'discord', 'label' => 'Application to Discord'],
            ['from' => 'app', 'to' => 'api', 'label' => 'Application to Trading API'],
            ['from' => 'app', 'to' => 'heatmap', 'label' => 'Application to Heatmap'],
        ],
    ];
}

/** Bot journal with one warning that has since recovered (shows the "recovered incident" state). */
function demo_journal(int $now): array
{
    $latest = [
        ['time' => gmdate('c', $now - 3 * 3600 - 420), 'priority' => 4, 'message' => 'Gateway heartbeat was late; reconnecting.'],
        ['time' => gmdate('c', $now - 3 * 3600 - 300), 'priority' => 4, 'message' => 'Rate limited on channel post, retrying in 2s.'],
    ];
    return ['ok' => true, 'window' => '24h', 'warning_count' => 2, 'error_count' => 0, 'latest_at' => $latest[1]['time'], 'latest' => $latest];
}

/**
 * Sample health-check export for /alerts in demo mode. Same shape as the file described in
 * src/Alerts/AlertState.php.
 */
function demo_alerts(int $now): array
{
    $iso = static fn (int $ts): string => gmdate('Y-m-d\TH:i:s\Z', $ts);
    return [
        'schema' => 'signalops-healthcheck-page-v1',
        'version' => 1,
        'host' => 'web',
        'generated_at_utc' => $iso($now - 40),
        'last_run_utc' => $iso($now - 40),
        'active' => [
            [
                'id' => 'disk:db:/var/lib/mysql',
                'severity' => 'P2',
                'title' => 'Disk usage above 80% on Database Server',
                'detail' => '/var/lib/mysql is 81.8% full (91 GB free).',
                'since' => $iso($now - 5 * 3600),
                'last_seen' => $iso($now - 40),
                'last_notified' => $iso($now - 5 * 3600),
                'count' => 150,
            ],
            [
                'id' => 'tls:status-page',
                'severity' => 'P3',
                'title' => 'TLS certificate expires in 12 days',
                'detail' => 'Renewal is scheduled; no action needed unless it is still open in a week.',
                'since' => $iso($now - 26 * 3600),
                'last_seen' => $iso($now - 40),
                'last_notified' => $iso($now - 26 * 3600),
                'count' => 780,
            ],
        ],
        'recent' => [
            [
                'kind' => 'recovered',
                'id' => 'service:discord:discord-bot.service',
                'severity' => 'P1',
                'title' => 'discord-bot.service was not active',
                'detail' => 'systemd restarted the unit; active again.',
                'since' => $iso($now - 3 * 3600 - 480),
                'at' => $iso($now - 3 * 3600 - 240),
            ],
            [
                'kind' => 'event',
                'id' => 'deploy:web',
                'severity' => null,
                'title' => 'Status page deployed',
                'detail' => 'New release switched in and passed the health check.',
                'since' => null,
                'at' => $iso($now - 9 * 3600),
            ],
            [
                'kind' => 'recovered',
                'id' => 'ping:app',
                'severity' => 'P2',
                'title' => 'Application Server ping loss',
                'detail' => '3 of 10 pings lost; back to 0% loss.',
                'since' => $iso($now - 30 * 3600),
                'at' => $iso($now - 29 * 3600 - 1200),
            ],
            [
                'kind' => 'event',
                'id' => 'reboot:heatmap',
                'severity' => 'P3',
                'title' => 'Heatmap Server rebooted',
                'detail' => 'Kernel update applied during the maintenance window.',
                'since' => null,
                'at' => $iso($now - 3 * 86400),
            ],
        ],
    ];
}
