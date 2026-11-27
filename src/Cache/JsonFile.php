<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

/**
 * Read a JSON snapshot written by a timer (status cache, alert state, ...). Never blocks on
 * anything slow: one stat + one bounded read.
 */
final class JsonFile
{
    /**
     * @return array{state: 'ok'|'missing'|'unreadable'|'invalid'|'too_large', data: array<mixed>|null, mtime: int|null}
     */
    public static function read(string $path, int $maxBytes = 4194304): array
    {
        \SignalOps\Status\Http\RequestStats::file();
        clearstatcache(true, $path);
        if ($path === '' || !file_exists($path)) {
            return ['state' => 'missing', 'data' => null, 'mtime' => null];
        }
        $size = @filesize($path);
        $mtime = @filemtime($path);
        $mtime = $mtime === false ? null : $mtime;
        if ($size !== false && $size > $maxBytes) {
            return ['state' => 'too_large', 'data' => null, 'mtime' => $mtime];
        }
        $raw = @file_get_contents($path, false, null, 0, max(0, $maxBytes) + 1);
        if (!is_string($raw)) {
            return ['state' => 'unreadable', 'data' => null, 'mtime' => $mtime];
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['state' => 'invalid', 'data' => null, 'mtime' => $mtime];
        }
        return ['state' => 'ok', 'data' => $data, 'mtime' => $mtime];
    }
}
