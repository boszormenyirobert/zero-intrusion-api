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

final class SharedProcessPollerAdditionalTest extends TestCase
{
    public function testGetCacheByProcessIdReturnsDecodedPayloadAndAliasMatches(): void
    {
        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::exactly(2))
            ->method('get')
            ->withConsecutive(['process-1'], ['process-1'])
            ->willReturn('{"process_check":true,"value":"ok"}');

        $service = new SharedProcessPoller(
            $cacheService,
            $this->createMock(SharedNotificationService::class),
            $this->createMock(AuthBridgeService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame(['process_check' => true, 'value' => 'ok'], $service->getCacheByProcessId('process-1'));
        self::assertSame(['process_check' => true, 'value' => 'ok'], $service->getChacheByProcessId('process-1'));
    }

    public function testPollTheRedisUsesApplicationListForApplicationType(): void
    {
        $authBridgeService = new class {
            public function fetchFromAccessTable(string $processId, string $type): array
            {
                return [
                    'response' => [['targetId' => 'target-1', 'application' => 'vault-app']],
                    'process' => ['process_check' => true, 'process' => true],
                ];
            }
        };

        $notificationService = $this->createMock(SharedNotificationService::class);
        $notificationService
            ->expects(self::once())
            ->method('getUserEmailByTargetId')
            ->willReturn(['email' => null, 'publicId' => null]);

        $service = new SharedProcessPoller(
            $this->createMock(ProcessStateCacheService::class),
            $notificationService,
            $this->createMock(AuthBridgeService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame([
            'applicationList' => [['targetId' => 'target-1', 'application' => 'vault-app']],
            'process_check' => true,
            'process' => true,
            'email' => null,
            'publicId' => null,
        ], $service->pollTheRedis('process-1', $authBridgeService, 'application'));
    }

    public function testPollTheRedisDefaultReturnsImmediatelyWhenCacheShowsCompletedProcess(): void
    {
        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::once())
            ->method('get')
            ->with('process-1')
            ->willReturn('{"process_check":true,"success":true}');

        $service = new SharedProcessPoller(
            $cacheService,
            $this->createMock(SharedNotificationService::class),
            $this->createMock(AuthBridgeService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame(['process_check' => true, 'success' => true], $service->pollTheRedisDefault('process-1'));
    }

    public function testPollTheRedisOneTouchReturnsEmptyArrayWhenProcessNeverAppears(): void
    {
        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::atLeastOnce())
            ->method('fetchForOneTouch')
            ->with('process-1', 'oneTouchProcessId')
            ->willReturn(false);

        $service = new SharedProcessPoller(
            $this->createMock(ProcessStateCacheService::class),
            $this->createMock(SharedNotificationService::class),
            $authBridgeService,
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame([], $service->pollTheRedisOneTouch('process-1', 'oneTouchProcessId'));
    }
}