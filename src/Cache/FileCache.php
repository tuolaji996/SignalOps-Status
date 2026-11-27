<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

/** JSON files in one directory. Writes go to a temp file and are renamed into place (atomic). */
final class FileCache extends AbstractCache
{
    public function __construct(
        private readonly string $dir,
        private readonly int $fileMode = 0640,
        ?\Closure $clock = null,
    ) {
        parent::__construct($clock);
    }

    public function path(string $key): string
    {
        $slug = substr(preg_replace('/[^A-Za-z0-9_-]+/', '-', $key) ?? 'key', 0, 60);
        return rtrim($this->dir, '/') . '/' . $slug . '-' . substr(sha1($key), 0, 16) . '.json';
    }

    public function get(string $key): ?CacheItem
    {
        $raw = @file_get_contents($this->path($key));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? CacheItem::fromArray($data) : null;
    }

    public function set(string $key, mixed $value, int $ttl, int $staleTtl = 0): bool
    {
        $now = $this->now();
        $item = new CacheItem($value, $now, $now + max(0, $ttl), $now + max(0, $ttl) + max(0, $staleTtl));
        $json = json_encode($item->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return false;
        }
        return self::writeAtomic($this->path($key), $json, $this->fileMode);
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    /** Public so TieredCache and timers can share the same cross-process lock. */
    public function lock(string $key): ?\Closure
    {
        if (!$this->ensureDir()) {
            return null;
        }
        $handle = @fopen($this->path($key) . '.lock', 'c');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return static function () use ($handle): void {
            flock($handle, LOCK_UN);
            fclose($handle);
        };
    }

    private function ensureDir(): bool
    {
        return is_dir($this->dir) || @mkdir($this->dir, 0750, true) || is_dir($this->dir);
    }

    /** Write $contents to $path via tmp file + rename. Usable on its own by timers. */
    public static function writeAtomic(string $path, string $contents, int $mode = 0640): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }
        $tmp = @tempnam($dir, '.tmp-');
        if ($tmp === false) {
            return false;
        }
        if (@file_put_contents($tmp, $contents, LOCK_EX) !== strlen($contents)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }
}
