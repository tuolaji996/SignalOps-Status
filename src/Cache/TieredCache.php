<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

/** APCu in front of the file cache: reads try memory first, writes go to both. */
final class TieredCache extends AbstractCache
{
    public function __construct(private readonly ApcuCache $memory, private readonly FileCache $files, ?\Closure $clock = null)
    {
        parent::__construct($clock);
    }

    public function get(string $key): ?CacheItem
    {
        $item = $this->memory->get($key);
        if ($item !== null) {
            return $item;
        }
        $item = $this->files->get($key);
        if ($item !== null) {
            $remaining = $item->staleUntil - $this->now();
            if ($remaining > 0) {
                $this->memory->prime($key, $item, $remaining);
            }
        }
        return $item;
    }

    public function set(string $key, mixed $value, int $ttl, int $staleTtl = 0): bool
    {
        $this->memory->set($key, $value, $ttl, $staleTtl);
        return $this->files->set($key, $value, $ttl, $staleTtl);
    }

    public function delete(string $key): void
    {
        $this->memory->delete($key);
        $this->files->delete($key);
    }

    protected function lock(string $key): ?\Closure
    {
        // The file lock is shared by every worker and by timers, so use it.
        return $this->files->lock($key);
    }
}
