<?php
declare(strict_types=1);

namespace SignalOps\Status\Config;

/**
 * Configuration for the routed pages.
 *
 * Same sources and precedence as load_signalops_config() in app/config.php:
 *   default_signalops_config() < $SIGNALOPS_CONFIG < /etc/signalops-status/config.php
 *   < config/signalops.php
 * Each file must `return [...]`. Unlike the classic loader, every file is required inside its own
 * empty scope, so a config file that happens to use $config, $path or $loaded cannot clobber the
 * loader's variables. Existing config files keep working unchanged.
 *
 * Defaults for keys only the routed pages read live in self::DEFAULTS (not in app/config.php), so
 * the classic dashboard sees exactly the config it saw before.
 */
final class Config
{
    public const DEFAULTS = [
        'app' => [
            // Demo mode: every page renders from built-in sample data with no other config.
            'demo' => true,
        ],
        'auth' => [
            // Password login for routes with 'auth' => true (e.g. /alerts).
            // Generate a hash with: php -r 'echo password_hash("your-password", PASSWORD_DEFAULT), "\n";'
            'enabled' => false,
            'password_hash' => '',
            'session_name' => 'signalops_session',
            'session_ttl_seconds' => 3600,
        ],
        'alerts' => [
            // JSON file written by an external health checker; read by /alerts.
            'export_path' => '/var/lib/signalops-status/alerts/page.json',
            // The checker counts as stale when the export is older than this.
            'stale_after_seconds' => 300,
        ],
        'ui' => [
            // Directory for page-data caches. '' = /dev/shm/signalops-status/ui (or the system temp dir).
            'cache_dir' => '',
        ],
    ];

    /** @param array<string, mixed> $values */
    public function __construct(private array $values)
    {
    }

    /** Config built from DEFAULTS plus $override only (tests). */
    public static function fromArray(array $override = []): self
    {
        $base = function_exists('default_signalops_config') ? self::merge(self::DEFAULTS, default_signalops_config()) : self::DEFAULTS;
        return new self(self::finish(self::merge($base, $override)));
    }

    /**
     * @param list<string>|null $files Override the file list (tests).
     */
    public static function load(string $projectRoot, ?array $files = null): self
    {
        $config = self::DEFAULTS;
        if (function_exists('default_signalops_config')) {
            $config = self::merge($config, default_signalops_config());
        }
        $files ??= self::defaultFiles($projectRoot);
        foreach ($files as $file) {
            if ($file === '' || !is_readable($file)) {
                continue;
            }
            $loaded = self::requireIsolated($file);
            if (is_array($loaded)) {
                $config = self::merge($config, $loaded);
            }
        }
        $config = self::finish($config);
        $timezone = (string)($config['app']['timezone'] ?? '');
        if ($timezone !== '' && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            date_default_timezone_set($timezone);
        }
        return new self($config);
    }

    /** @return list<string> */
    public static function defaultFiles(string $projectRoot): array
    {
        return [
            (string)(getenv('SIGNALOPS_CONFIG') ?: ''),
            '/etc/signalops-status/config.php',
            rtrim($projectRoot, '/') . '/config/signalops.php',
        ];
    }

    /** @param array<string, mixed> $config */
    private static function finish(array $config): array
    {
        $config['refresh_seconds'] = max(10, min(300, (int)($config['refresh_seconds'] ?? 30) ?: 30));
        return $config;
    }

    private static function requireIsolated(string $__file): mixed
    {
        return (static function () use ($__file): mixed {
            return require $__file;
        })();
    }

    /**
     * Recursive merge with the same semantics as merge_config() in app/helpers.php: associative
     * arrays merge, lists and scalars are replaced.
     *
     * @param array<mixed> $base
     * @param array<mixed> $override
     * @return array<mixed>
     */
    public static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && self::isAssoc($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /** @param array<mixed> $value */
    private static function isAssoc(array $value): bool
    {
        return $value !== [] && array_keys($value) !== range(0, count($value) - 1);
    }

    /** Dotted lookup: $config->get('alerts.export_path'). */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Demo mode (app.demo): pages show built-in sample data. */
    public function demo(): bool
    {
        return filter_var($this->get('app.demo', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** The whole array, for passing to app/*.php functions that expect $config. */
    public function all(): array
    {
        return $this->values;
    }
}
