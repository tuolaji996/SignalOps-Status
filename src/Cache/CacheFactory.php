<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

final class CacheFactory
{
    /** File cache in $dir; with APCu in front when the extension is loaded. */
    public static function create(string $dir): Cache
    {
        $files = new FileCache($dir);
        if (ApcuCache::available()) {
            return new TieredCache(new ApcuCache(ApcuCache::DEFAULT_PREFIX), $files);
        }
        return $files;
    }

    /** Default directory for UI caches: config ui.cache_dir, else /dev/shm (or the system temp dir). */
    public static function defaultDir(string $configured = ''): string
    {
        if (trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }
        if (DIRECTORY_SEPARATOR === '/' && is_dir('/dev/shm') && is_writable('/dev/shm')) {
            return '/dev/shm/signalops-status/ui';
        }
        return rtrim(sys_get_temp_dir(), '/') . '/signalops-status-ui';
    }
}
