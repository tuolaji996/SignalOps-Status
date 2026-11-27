<?php
declare(strict_types=1);

namespace SignalOps\Status\Http;

/**
 * Per-request numbers for the page footer, e.g. "Processed in 0.0031 s · 3 cache reads (2 hits)
 * · 1 file read · Memory 2.1 MB". Server software and versions are deliberately not reported:
 * the footer is public. FPM handles one request per
 * worker at a time, so plain static counters are per request (reset() at the start).
 */
final class RequestStats
{
    private static ?float $start = null;
    private static int $cacheReads = 0;
    private static int $cacheHits = 0;
    private static int $fileReads = 0;
    private static int $queries = 0;

    public static function reset(?float $start = null): void
    {
        $fromServer = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : null;
        self::$start = $start ?? ($fromServer !== null && PHP_SAPI !== 'cli' ? $fromServer : microtime(true));
        self::$cacheReads = self::$cacheHits = self::$fileReads = self::$queries = 0;
    }

    /** Cache lookups through Cache::remember(); $state is hit, stale or miss. */
    public static function cache(string $state): void
    {
        self::$cacheReads++;
        if ($state === 'hit' || $state === 'stale') {
            self::$cacheHits++;
        }
    }

    /** JSON snapshots read from disk (status cache, alert export, ...). */
    public static function file(): void
    {
        self::$fileReads++;
    }

    /** SQL statements a page sends (optional database panels). */
    public static function query(): void
    {
        self::$queries++;
    }

    public static function elapsed(?float $now = null): float
    {
        self::$start ??= microtime(true);
        return max(0.0, ($now ?? microtime(true)) - self::$start);
    }

    /**
     * @return array{seconds: float, cache_reads: int, cache_hits: int, file_reads: int, queries: int, memory_mb: float}
     */
    public static function snapshot(): array
    {
        return [
            'seconds' => self::elapsed(),
            'cache_reads' => self::$cacheReads,
            'cache_hits' => self::$cacheHits,
            'file_reads' => self::$fileReads,
            'queries' => self::$queries,
            'memory_mb' => round(memory_get_peak_usage() / 1048576, 1),
        ];
    }
}
