<?php
declare(strict_types=1);

namespace SignalOps\Status\Cache;

/** One cached value plus its timestamps (unix seconds). */
final class CacheItem
{
    public function __construct(
        public readonly mixed $value,
        public readonly int $storedAt,
        public readonly int $expiresAt,
        public readonly int $staleUntil,
        /** 'hit' (fresh), 'stale' (served while a refresh runs), 'miss' (computed now). */
        public readonly string $state = 'hit',
    ) {
    }

    public function age(?int $now = null): int
    {
        return max(0, ($now ?? time()) - $this->storedAt);
    }

    public function isFresh(?int $now = null): bool
    {
        return ($now ?? time()) < $this->expiresAt;
    }

    /** Fresh, or expired but still inside the stale window. */
    public function isUsable(?int $now = null): bool
    {
        return ($now ?? time()) < $this->staleUntil;
    }

    public function withState(string $state): self
    {
        return new self($this->value, $this->storedAt, $this->expiresAt, $this->staleUntil, $state);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['stored_at' => $this->storedAt, 'expires_at' => $this->expiresAt, 'stale_until' => $this->staleUntil, 'value' => $this->value];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        if (!isset($data['stored_at'], $data['expires_at'], $data['stale_until']) || !array_key_exists('value', $data)) {
            return null;
        }
        return new self($data['value'], (int)$data['stored_at'], (int)$data['expires_at'], (int)$data['stale_until']);
    }
}
