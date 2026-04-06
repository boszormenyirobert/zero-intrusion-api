<?php

namespace App\Service\Cache;

use Psr\Cache\CacheItemPoolInterface;

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

    public function set(string $processId, mixed $value, int $ttl = 3600): void
    {
        $item = $this->cachePool->getItem($this->getCacheKey($processId));
        $item->set($value);
        $item->expiresAfter($ttl);

        $this->cachePool->save($item);
    }
}