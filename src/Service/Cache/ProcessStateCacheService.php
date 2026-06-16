<?php

namespace App\Service\Cache;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

class ProcessStateCacheService
{
    public function __construct(
        private readonly CacheItemPoolInterface $cachePool
    ) {}

    public function getCacheKey(string $processId): string
    {
        return sprintf('process_state.%s', $processId);
    }

    public function has(string $processId): bool
    {
        return $this->cachePool->hasItem($this->getCacheKey($processId));
    }

    public function get(string $processId): mixed
    {
        $item = $this->cachePool->getItem($this->getCacheKey($processId));

        return $item->isHit() ? $item->get() : null;
    }

    public function set(string $processId, mixed $value, int $ttl = 3600): bool
    {
        $item = $this->cachePool->getItem($this->getCacheKey($processId));
        if(!$this->getCacheKey($processId)) {
            throw new RuntimeException(sprintf('Failed to create cache key for processId: %s', $processId));
        }

        $item->set($value);
        $item->expiresAfter($ttl);

        if (!$this->cachePool->save($item)) {
            throw new RuntimeException(sprintf('Failed to save process state cache for processId: %s', $processId));
        }

        return true;
    }
}