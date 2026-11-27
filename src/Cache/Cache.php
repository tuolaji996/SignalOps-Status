<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

/**
 * Cache for page data. Values must be JSON-serialisable (arrays/scalars), because the file
 * store writes JSON. Get an instance from CacheFactory::create() or $app->cache().
 */
interface Cache
{
    public function get(string $key): ?CacheItem;

    /** Atomic write. $ttl = seconds the value is fresh; $staleTtl = extra seconds it may be served stale. */
    public function set(string $key, mixed $value, int $ttl, int $staleTtl = 0): bool;

    public function delete(string $key): void;

    /**
     * Fresh value -> returned as is. Stale but usable -> returned immediately and, when
     * $refreshInBackground, recomputed after the response is sent (one worker at a time).
     * Missing or too old -> computed now. If computing throws and a stale copy exists, the stale
     * copy is returned instead of failing the page.
     *
     * @param callable(): mixed $compute
     */
    public function remember(string $key, int $ttl, int $staleTtl, callable $compute, bool $refreshInBackground = true): CacheItem;
}
