<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler\Application;

use App\DTO\AuthBridge\Application\FetchFromAccessTableResultDTO;
use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryState;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Fetch;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\Crypters\CrypterDatabaseLoginService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class FetchTest extends TestCase
{
    public function testFetchForOneTouchReturnsFalseWhenCacheEntryMissing(): void
    {
        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('get')
            ->with('one-touch-1')
            ->willReturn(null);

        $service = $this->createService(cache: $cache);

        self::assertFalse($service->fetchForOneTouch('one-touch-1', 'oneTouchProcessId'));
    }

    public function testFetchForOneTouchReturnsFalseForInvalidCachedJson(): void
    {
        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('get')
            ->with('one-touch-1')
            ->willReturn('{invalid-json');

        $service = $this->createService(cache: $cache);

        self::assertFalse($service->fetchForOneTouch('one-touch-1', 'oneTouchProcessId'));
    }

    public function testFetchFromAccessTableOrFailMapsApplicationResponses(): void
    {
        $cachePayload = json_encode([
            'applicationProcessId' => 'app-123',
            'iv' => 'iv-value',
            'applications' => 'encrypted',
        ], JSON_THROW_ON_ERROR);

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('get')
            ->with('app-123')
            ->willReturn($cachePayload);

        $decrypted = (new AuthBridge())->setApplications(json_encode([
            (object) [
                'application' => 'mail',
                'decrypted' => 'secret',
                'description' => 'Mail account',
                'targetId' => 'target-1',
            ],
        ], JSON_THROW_ON_ERROR));

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->willReturn($decrypted);

        $service = $this->createService(cache: $cache, crypter: $crypter, logger: $this->createMock(LoggerInterface::class));
        $result = $service->fetchFromAccessTableOrFail('app-123', 'application');

        self::assertEquals(new FetchFromAccessTableResultDTO(
            ['process' => true, 'validation' => true, 'process_check' => true],
            [[
                'application' => 'mail',
                'userCredential' => 'secret',
                'description' => 'Mail account',
                'targetId' => 'target-1',
            ]]
        ), $result);
    }

    public function testFetchFromAccessTableOrFailReturnsErrorResponseForInvalidApplicationsJson(): void
    {
        $cachePayload = json_encode([
            'applicationProcessId' => 'app-123',
            'iv' => 'iv-value',
            'applications' => 'encrypted',
        ], JSON_THROW_ON_ERROR);

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('get')
            ->with('app-123')
            ->willReturn($cachePayload);

        $decrypted = (new AuthBridge())->setApplications('{invalid-json');

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->willReturn($decrypted);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects(self::once())
            ->method('serialize');

        $service = $this->createService(cache: $cache, crypter: $crypter, logger: $logger, serializer: $serializer);
        $result = $service->fetchFromAccessTableOrFail('app-123', 'application');

        self::assertEquals(new FetchFromAccessTableResultDTO(
            ['process' => true, 'validation' => true, 'process_check' => true],
            ['error' => 'Failed to process application data']
        ), $result);
    }

    private function createService(
        ?ProcessStateCacheService $cache = null,
        ?CrypterDatabaseLoginService $crypter = null,
        ?LoggerInterface $logger = null,
        ?SerializerInterface $serializer = null,
    ): Fetch {
        return new Fetch(
            $this->createMock(AuthBridgeRepository::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $crypter ?? $this->createMock(CrypterDatabaseLoginService::class),
            $serializer ?? $this->createMock(SerializerInterface::class),
            new RegistryState(
                $this->createMock(AuthBridgeRepository::class),
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(LoggerInterface::class),
            ),
            $cache ?? $this->createMock(ProcessStateCacheService::class),
        );
    }
}
