<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

abstract class AbstractCache implements Cache
{
    /** @var \Closure(): int */
    protected \Closure $clock;

    /** @var array<string, true> */
    private static array $scheduled = [];

    public function __construct(?\Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): int => time();
    }

    protected function now(): int
    {
        return ($this->clock)();
    }

    /** Try to take a non-blocking refresh lock; returns a release callback or null if busy. */
    abstract protected function lock(string $key): ?\Closure;

    public function remember(string $key, int $ttl, int $staleTtl, callable $compute, bool $refreshInBackground = true): CacheItem
    {
        $now = $this->now();
        $item = $this->get($key);
        if ($item !== null && $item->isFresh($now)) {
            \SignalOps\Status\Http\RequestStats::cache('hit');
            return $item->withState('hit');
        }
        if ($item !== null && $item->isUsable($now)) {
            if ($refreshInBackground) {
                $this->scheduleRefresh($key, $ttl, $staleTtl, $compute);
            }
            \SignalOps\Status\Http\RequestStats::cache('stale');
            return $item->withState('stale');
        }
        \SignalOps\Status\Http\RequestStats::cache('miss');
        try {
            $value = $compute();
        } catch (\Throwable $error) {
            if ($item !== null) {
                return $item->withState('stale');
            }
            throw $error;
        }
        $this->set($key, $value, $ttl, $staleTtl);
        return new CacheItem($value, $now, $now + $ttl, $now + $ttl + $staleTtl, 'miss');
    }

    /** Recompute now under the refresh lock. Returns false when another worker holds it. */
    public function refresh(string $key, int $ttl, int $staleTtl, callable $compute): bool
    {
        $release = $this->lock($key);
        if ($release === null) {
            return false;
        }
        try {
            $this->set($key, $compute(), $ttl, $staleTtl);
            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            $release();
        }
    }

    private function scheduleRefresh(string $key, int $ttl, int $staleTtl, callable $compute): void
    {
        $id = static::class . spl_object_id($this) . ':' . $key;
        if (isset(self::$scheduled[$id])) {
            return;
        }
        self::$scheduled[$id] = true;
        register_shutdown_function(function () use ($key, $ttl, $staleTtl, $compute): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            ignore_user_abort(true);
            $this->refresh($key, $ttl, $staleTtl, $compute);
        });
    }
}
