<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub;

use App\Entity\AuthBridge;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\SharedProcessPoller;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SharedProcessPollerTest extends TestCase
{
    public function testGetCacheByProcessIdReturnsEmptyArrayForInvalidJson(): void
    {
        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::once())
            ->method('get')
            ->with('process-1')
            ->willReturn('{invalid');

        $service = new SharedProcessPoller(
            $cacheService,
            $this->createMock(SharedNotificationService::class),
            $this->createMock(AuthBridgeService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame([], $service->getCacheByProcessId('process-1'));
    }

    public function testPollTheRedisMergesResponseAndNotificationData(): void
    {
        $authBridgeService = new class {
            public function fetchFromAccessTable(string $processId, string $type): array
            {
                return [
                    'response' => [['targetId' => 'target-1']],
                    'process' => ['process_check' => true],
                ];
            }
        };

        $notificationService = $this->createMock(SharedNotificationService::class);
        $notificationService
            ->expects(self::once())
            ->method('getUserEmailByTargetId')
            ->with([
                'response' => [['targetId' => 'target-1']],
                'process' => ['process_check' => true],
            ])
            ->willReturn(['email' => 'user@example.test', 'publicId' => 'public-1']);

        $service = new SharedProcessPoller(
            $this->createMock(ProcessStateCacheService::class),
            $notificationService,
            $this->createMock(AuthBridgeService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame([
            'domainList' => [['targetId' => 'target-1']],
            'process_check' => true,
            'email' => 'user@example.test',
            'publicId' => 'public-1',
        ], $service->pollTheRedis('process-1', $authBridgeService, 'domain'));
    }

    public function testPollTheRedisOneTouchReturnsUserPayloadImmediately(): void
    {
        $user = (new AuthBridge())
            ->setUserIdentity(json_encode(['email' => 'user@example.test', 'publicId' => 'public-1'], JSON_THROW_ON_ERROR));

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('fetchForOneTouch')
            ->with('process-1', 'oneTouchProcessId')
            ->willReturn($user);

        $service = new SharedProcessPoller(
            $this->createMock(ProcessStateCacheService::class),
            $this->createMock(SharedNotificationService::class),
            $authBridgeService,
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame([
            'email' => 'user@example.test',
            'publicId' => 'public-1',
        ], $service->pollTheRedisOneTouch('process-1', 'oneTouchProcessId'));
    }
}