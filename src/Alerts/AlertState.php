<?php
declare(strict_types=1);

namespace SignalOps\Status\Alerts;

use SignalOps\Status\Cache\JsonFile;
use SignalOps\Status\I18n\I18n;

/**
 * Reads the alert export written by an external health checker for the /alerts page. The checker
 * owns alerting (deciding what is wrong, notifying people); the status page only shows its last
 * export. Any tool can produce the file: a cron script, a monitoring agent, a systemd timer.
 *
 * Config: alerts.export_path (default /var/lib/signalops-status/alerts/page.json) and
 * alerts.stale_after_seconds (default 300; older last_run_utc shows a "health check is late"
 * notice). The web server user must be able to read the file (e.g. mode 0640, group www-data).
 * Write it atomically (temp file + rename). Max size 1 MiB.
 *
 * Format (JSON, schema "signalops-healthcheck-page-v1"):
 *
 *   {
 *     "schema": "signalops-healthcheck-page-v1",
 *     "version": 1,
 *     "host": "web",                              // name of the host running the checker (shown)
 *     "generated_at_utc": "2026-01-01T12:00:00Z", // when this file was written
 *     "last_run_utc": "2026-01-01T12:00:00Z",     // when the last check run finished
 *     "active": [                                  // open problems
 *       {"id": "disk:db:/var/lib/mysql",         // stable check id (required)
 *        "severity": "P0|P1|P2|P3",              // P0 most urgent; unknown values count as P1
 *        "title": "Disk usage above 80%",         // short text (defaults to id)
 *        "detail": "…",                           // optional longer text
 *        "since": "<ISO 8601>",                   // when the problem started
 *        "last_seen": "<ISO 8601>",               // last run that still saw it
 *        "last_notified": "<ISO 8601>|null",      // last notification sent
 *        "count": 42}                             // number of runs that saw it
 *     ],
 *     "recent": [                                  // newest first, about the last 7 days, ≤ 50
 *       {"kind": "recovered|event", "id": "…", "severity": "P0..P3|null",
 *        "title": "…", "detail": "…",
 *        "since": "<ISO 8601>|null",              // start of the problem (recovered) or null
 *        "at": "<ISO 8601>"}                      // when it recovered / happened
 *     ]
 *   }
 *
 * Timestamps may be ISO 8601 strings or Unix seconds. Titles and details are shown as data (not
 * translated) and must not contain secrets. In demo mode (app.demo) the sample from demo_alerts()
 * in app/demo.php is shown instead of the file.
 */
final class AlertState
{
    public const SCHEMA = 'signalops-healthcheck-page-v1';
    public const DEFAULT_PATH = '/var/lib/signalops-status/alerts/page.json';
    public const SEVERITIES = ['P0', 'P1', 'P2', 'P3'];
    public const MAX_BYTES = 1048576;

    /**
     * @return array{
     *   state: 'ok'|'missing'|'unreadable'|'invalid'|'too_large'|'schema',
     *   path: string, file_mtime: int|null, generated_at: int|null, host: string,
     *   checker: array{last_run: int|null, age: int|null, stale: bool},
     *   active: list<array<string, mixed>>, recent: list<array<string, mixed>>
     * }
     */
    public static function load(string $path, int $now, int $staleAfterSeconds = 300): array
    {
        $file = JsonFile::read($path, self::MAX_BYTES);
        return self::fromData($file['state'] === 'ok' ? $file['data'] : null, (string)$file['state'], $path, $file['mtime'], $now, $staleAfterSeconds);
    }

    /** Demo mode: the sample export from app/demo.php. */
    public static function demo(int $now, int $staleAfterSeconds = 300): array
    {
        $data = function_exists('demo_alerts') ? demo_alerts($now) : null;
        return self::fromData($data, $data === null ? 'missing' : 'ok', 'demo', $now, $now, $staleAfterSeconds);
    }

    /** @param array<string, mixed>|null $data decoded export */
    public static function fromData(?array $data, string $fileState, string $path, ?int $mtime, int $now, int $staleAfterSeconds = 300): array
    {
        $result = [
            'state' => $fileState,
            'path' => $path,
            'file_mtime' => $mtime,
            'generated_at' => null,
            'host' => '',
            'checker' => ['last_run' => null, 'age' => null, 'stale' => true],
            'active' => [],
            'recent' => [],
        ];
        if ($fileState !== 'ok' || $data === null) {
            $result['state'] = $fileState === 'ok' ? 'invalid' : $fileState;
            return $result;
        }
        if (($data['schema'] ?? null) !== self::SCHEMA) {
            $result['state'] = 'schema';
            return $result;
        }

        $lastRun = I18n::timestamp($data['last_run_utc'] ?? null);
        $result['generated_at'] = I18n::timestamp($data['generated_at_utc'] ?? null);
        $result['host'] = self::text($data['host'] ?? '', 100);
        $result['checker'] = [
            'last_run' => $lastRun,
            'age' => $lastRun === null ? null : max(0, $now - $lastRun),
            'stale' => $lastRun === null || $now - $lastRun > $staleAfterSeconds,
        ];

        foreach (array_slice((array)($data['active'] ?? []), 0, 500) as $row) {
            if (is_array($row) && ($alert = self::normalize($row)) !== null) {
                $alert['last_seen'] = I18n::timestamp($row['last_seen'] ?? null);
                $alert['last_notified'] = I18n::timestamp($row['last_notified'] ?? null);
                $alert['count'] = max(0, (int)($row['count'] ?? 0));
                $result['active'][] = $alert;
            }
        }
        // Most severe first, then the longest-running.
        usort($result['active'], static fn (array $a, array $b): int => [$a['severity'], $a['since'] ?? PHP_INT_MAX] <=> [$b['severity'], $b['since'] ?? PHP_INT_MAX]);

        foreach (array_slice((array)($data['recent'] ?? []), 0, 200) as $row) {
            if (!is_array($row) || !in_array($row['kind'] ?? null, ['recovered', 'event'], true) || ($alert = self::normalize($row)) === null) {
                continue;
            }
            $alert['kind'] = $row['kind'];
            $alert['at'] = I18n::timestamp($row['at'] ?? null);
            if (!in_array($row['severity'] ?? null, self::SEVERITIES, true)) {
                $alert['severity'] = null;
            }
            $result['recent'][] = $alert;
        }
        usort($result['recent'], static fn (array $a, array $b): int => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));
        return $result;
    }

    /** @param array<string, mixed> $row */
    private static function normalize(array $row): ?array
    {
        $id = $row['id'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            return null;
        }
        return [
            'id' => self::text($id, 200),
            'severity' => in_array($row['severity'] ?? null, self::SEVERITIES, true) ? $row['severity'] : 'P1',
            'title' => self::text($row['title'] ?? '', 300) ?: self::text($id, 200),
            'detail' => self::text($row['detail'] ?? '', 600),
            'since' => I18n::timestamp($row['since'] ?? null),
        ];
    }

    private static function text(mixed $value, int $limit): string
    {
        return is_string($value) ? mb_substr(trim($value), 0, $limit) : '';
    }

    /** UI tone: P0/P1 amber (needs attention), P2/P3 blue (informational), unknown grey. */
    public static function tone(?string $severity): string
    {
        return match ($severity) {
            'P0', 'P1' => 'warn',
            'P2', 'P3' => 'info',
            default => 'neutral',
        };
    }
}
