<?php

declare(strict_types=1);

namespace App\Tests\Service\Cache;

use App\Service\Cache\ProcessStateCacheService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

final class ProcessStateCacheServiceTest extends TestCase
{
    public function testGetCacheKeyAndHasDelegateToCachePool(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::once())
            ->method('hasItem')
            ->with('process_state.process-1')
            ->willReturn(true);

        $service = new ProcessStateCacheService($pool);

        self::assertSame('process_state.process-1', $service->getCacheKey('process-1'));
        self::assertTrue($service->has('process-1'));
    }

    public function testGetReturnsCachedValueOnlyOnHit(): void
    {
        $hitItem = $this->createCacheItem(true, ['ok' => true]);
        $missItem = $this->createCacheItem(false, ['ignored' => true]);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->expects(self::exactly(2))
            ->method('getItem')
            ->withConsecutive(['process_state.process-1'], ['process_state.process-2'])
            ->willReturnOnConsecutiveCalls($hitItem, $missItem);

        $service = new ProcessStateCacheService($pool);

        self::assertSame(['ok' => true], $service->get('process-1'));
        self::assertNull($service->get('process-2'));
    }

    public function testSetThrowsWhenCacheCannotBeSaved(): void
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->expects(self::once())->method('set')->with(['state' => true])->willReturnSelf();
        $item->expects(self::once())->method('expiresAfter')->with(90)->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool->expects(self::once())->method('getItem')->with('process_state.process-1')->willReturn($item);
        $pool->expects(self::once())->method('save')->with($item)->willReturn(false);

        $service = new ProcessStateCacheService($pool);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to save process state cache for processId: process-1');

        $service->set('process-1', ['state' => true], 90);
    }

    private function createCacheItem(bool $isHit, mixed $value): CacheItemInterface&MockObject
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn($isHit);
        $item->method('get')->willReturn($value);

        return $item;
    }
}