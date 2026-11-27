<?php
return [
    'app' => [
        'name' => 'SignalOps Status',
        'subtitle' => 'PHP Based status dashboard for Discord bots, trading services, and private server fleets.',
        'timezone' => 'America/Toronto',
        'demo' => false,
        'theme' => [
            'accent' => '#20b981',
            'repository_url' => 'https://github.com/tuolaji996/SignalOps-Status',
            'owner_name' => 'Your Team',
            'owner_url' => 'https://example.com',
        ],
    ],
    // Password login for the pages marked with a lock (e.g. /alerts). Without a hash and with
    // app.demo = true those pages stay open; with app.demo = false they show "not configured".
    // Generate the hash with: php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
    'auth' => [
        'enabled' => true,
        'password_hash' => 'put-password-hash-here',
        'session_name' => 'signalops_session',
        'session_ttl_seconds' => 3600,
    ],
    // /alerts reads a JSON export written by an external health checker.
    'alerts' => [
        'export_path' => '/var/lib/signalops-status/alerts/page.json',
        // Show a warning when the export is older than this many seconds.
        'stale_after_seconds' => 300,
    ],
    // Routed pages (/overview, /servers, ...).
    'ui' => [
        // Page-data cache directory; '' = /dev/shm/signalops-status/ui or the system temp dir.
        'cache_dir' => '/dev/shm/signalops-status/ui',
    ],
    'cache' => [
        'enabled' => true,
        'seconds' => 90,
        'stale_seconds' => 300,
        'serve_expired_seconds' => 604800,
        'refresh_lock_seconds' => 85,
        'browser_max_age' => 5,
        'stale_while_revalidate' => 300,
        'php_cli' => '/usr/bin/php',
        'refresh_lock_path' => '/dev/shm/signalops-status/status-cache.refresh.lock',
        'cdn' => [
            'enabled' => false,
            'edge_max_age' => 90,
            'stale_while_revalidate' => 300,
            'stale_if_error' => 604800,
        ],
        'path' => '/dev/shm/signalops-status/status-cache.json',
    ],
    'sla' => [
        'enabled' => true,
        'path' => '/var/lib/signalops-status/ping-sla.json',
        'ping_timeout_seconds' => 1,
    ],
    'traffic' => [
        'enabled' => true,
        'path' => '/var/lib/signalops-status/network-usage.json',
    ],
    'database' => [
        'enabled' => true,
        'label' => 'Database Server',
        'dsn' => 'mysql:host=DATABASE_PRIVATE_HOST;port=3306;dbname=signals;charset=utf8mb4',
        'user' => 'status_readonly',
        'password' => 'put-readonly-password-here',
        'summary_sql' => "SELECT COUNT(*) AS event_count, MAX(seen_at) AS latest_event_at FROM signal_events",
        'recent_sql' => "SELECT seen_at, kind, symbol, text FROM signal_events ORDER BY seen_at DESC LIMIT 8",
    ],
    'endpoints' => [
        'discord' => [
            'label' => 'Discord Bot',
            'url' => 'http://DISCORD_BOT_PRIVATE_HOST:8790/health.json',
            'kind' => 'discord',
        ],
        'api' => [
            'label' => 'Trading API',
            'url' => 'http://TRADING_API_PRIVATE_HOST:8787/health.json',
            'kind' => 'api',
        ],
    ],
    'machines' => [
        [
            'key' => 'web',
            'label' => 'Web Server',
            'role' => 'Status frontend',
            'location' => 'Los Angeles, CA',
            'kind' => 'local',
            'network_iface' => 'tailscale0',
            'sla_host' => '127.0.0.1',
            'latency_targets' => [
                'db' => 'DATABASE_PRIVATE_HOST',
                'discord' => 'DISCORD_BOT_PRIVATE_HOST',
                'api' => 'TRADING_API_PRIVATE_HOST',
            ],
            'disks' => ['/'],
            'services' => ['nginx'],
        ],
        [
            'key' => 'db',
            'label' => 'Database Server',
            'role' => 'Long-term archive',
            'location' => 'Chicago, IL',
            'kind' => 'ssh',
            'host' => 'DATABASE_PRIVATE_HOST',
            'sla_host' => 'DATABASE_PRIVATE_HOST',
            'user' => 'root',
            'key_file' => '/etc/signalops-status/status_probe_ed25519',
            'ssh_timeout_seconds' => 4,
            'ssh_connect_timeout_seconds' => 2,
            'latency_targets' => [
                'web' => 'WEB_PRIVATE_HOST',
                'discord' => 'DISCORD_BOT_PRIVATE_HOST',
                'api' => 'TRADING_API_PRIVATE_HOST',
            ],
            'disks' => ['/', '/var/lib/mysql'],
            'services' => ['mysql'],
        ],
        [
            'key' => 'discord',
            'label' => 'Discord Bot Server',
            'role' => 'Bot runtime',
            'location' => 'Toronto, ON',
            'kind' => 'ssh',
            'host' => 'DISCORD_BOT_PRIVATE_HOST',
            'sla_host' => 'DISCORD_BOT_PRIVATE_HOST',
            'user' => 'root',
            'key_file' => '/etc/signalops-status/status_probe_ed25519',
            'ssh_timeout_seconds' => 4,
            'ssh_connect_timeout_seconds' => 2,
            'latency_targets' => [
                'web' => 'WEB_PRIVATE_HOST',
                'db' => 'DATABASE_PRIVATE_HOST',
                'api' => 'TRADING_API_PRIVATE_HOST',
            ],
            'disks' => ['/opt/discord-bot', '/'],
            'services' => ['discord-bot.service'],
            'journal_units' => ['discord-bot.service'],
        ],
        [
            'key' => 'api',
            'label' => 'Trading API Server',
            'role' => 'Market data capture',
            'location' => 'Toronto, ON',
            'kind' => 'ssh',
            'host' => 'TRADING_API_PRIVATE_HOST',
            'sla_host' => 'TRADING_API_PRIVATE_HOST',
            'user' => 'root',
            'key_file' => '/etc/signalops-status/status_probe_ed25519',
            'ssh_timeout_seconds' => 4,
            'ssh_connect_timeout_seconds' => 2,
            'latency_targets' => [
                'web' => 'WEB_PRIVATE_HOST',
                'db' => 'DATABASE_PRIVATE_HOST',
                'discord' => 'DISCORD_BOT_PRIVATE_HOST',
            ],
            'disks' => ['/'],
            'services' => ['trading-api.service'],
            'journal_units' => ['trading-api.service'],
        ],
    ],
    'latency_map' => [
        'enabled' => true,
        'nodes' => [
            'web' => ['label' => 'Web Server', 'location' => 'Los Angeles, CA', 'x' => 15, 'y' => 65],
            'db' => ['label' => 'Database Server', 'location' => 'Chicago, IL', 'x' => 58, 'y' => 45],
            'discord' => ['label' => 'Discord Bot Server', 'location' => 'Toronto, ON', 'x' => 72, 'y' => 62],
            'api' => ['label' => 'Trading API Server', 'location' => 'Toronto, ON', 'x' => 70, 'y' => 28],
        ],
        'links' => [
            ['from' => 'web', 'to' => 'db', 'source' => 'web', 'target' => 'db', 'label' => 'Web to Database'],
            ['from' => 'web', 'to' => 'discord', 'source' => 'web', 'target' => 'discord', 'label' => 'Web to Discord'],
            ['from' => 'web', 'to' => 'api', 'source' => 'web', 'target' => 'api', 'label' => 'Web to Trading API'],
            ['from' => 'db', 'to' => 'discord', 'source' => 'db', 'target' => 'discord', 'label' => 'Database to Discord'],
            ['from' => 'discord', 'to' => 'api', 'source' => 'discord', 'target' => 'api', 'label' => 'Discord to Trading API'],
        ],
    ],
];
