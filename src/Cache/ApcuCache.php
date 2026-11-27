<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

/** In-memory cache. Only constructed when the APCu extension is loaded and enabled. */
final class ApcuCache extends AbstractCache
{
    public const DEFAULT_PREFIX = 'sostatus:';

    public function __construct(private readonly string $prefix = self::DEFAULT_PREFIX, ?\Closure $clock = null)
    {
        parent::__construct($clock);
    }

    public static function available(): bool
    {
        return function_exists('apcu_fetch') && function_exists('apcu_enabled') && apcu_enabled();
    }

    public function get(string $key): ?CacheItem
    {
        $ok = false;
        $data = apcu_fetch($this->prefix . $key, $ok);
        return $ok && is_array($data) ? CacheItem::fromArray($data) : null;
    }

    public function set(string $key, mixed $value, int $ttl, int $staleTtl = 0): bool
    {
        $now = $this->now();
        $item = new CacheItem($value, $now, $now + max(0, $ttl), $now + max(0, $ttl) + max(0, $staleTtl));
        return (bool)apcu_store($this->prefix . $key, $item->toArray(), max(1, $ttl + $staleTtl));
    }

    /** Store an existing item (TieredCache copies file hits into memory). */
    public function prime(string $key, CacheItem $item, int $ttl): void
    {
        apcu_store($this->prefix . $key, $item->toArray(), max(1, $ttl));
    }

    public function delete(string $key): void
    {
        apcu_delete($this->prefix . $key);
    }

    protected function lock(string $key): ?\Closure
    {
        $lockKey = $this->prefix . 'lock:' . $key;
        if (!apcu_add($lockKey, 1, 120)) {
            return null;
        }
        return static function () use ($lockKey): void {
            apcu_delete($lockKey);
        };
    }
}
