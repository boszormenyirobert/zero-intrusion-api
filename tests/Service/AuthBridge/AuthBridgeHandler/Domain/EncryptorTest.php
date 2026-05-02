<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler\Domain;

use App\Entity\AccessRegistry;
use App\Entity\AuthBridge;
use App\Repository\AccessRegistryRepository;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor as ApplicationEncryptor;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use App\Service\Firebase\FirebaseService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class EncryptorTest extends TestCase
{
    public function testSetDecryptedValuesForDomainReturnsFalseWhenNoCredentialsFound(): void
    {
        $accessRegistryRepository = $this->createMock(AccessRegistryRepository::class);
        $accessRegistryRepository->expects(self::never())->method('findBy');

        $service = $this->createService(accessRegistryRepository: $accessRegistryRepository);

        self::assertFalse($service->setDecryptedValuesForDomain(['credentials' => []]));
    }

    public function testGetDecryptedCredentialsReturnsEmptyArrayWhenNoMatchingDomainExists(): void
    {
        $crypterDatabaseUserService = new CrypterDatabaseAccessRegistryService(
            $this->createParameterBag(),
            $this->createMock(LoggerInterface::class),
        );
        $page = $crypterDatabaseUserService->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-123',
            'registrationProcessId' => 'reg-123',
            'targetId' => 'target-1',
            'domain' => 'other.example',
            'userCredential' => 'credential-value',
            'description' => 'Other domain',
        ], 'domain');

        $accessRegistryRepository = $this->createMock(AccessRegistryRepository::class);
        $accessRegistryRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-123'])
            ->willReturn([$page]);

        $service = $this->createService(
            accessRegistryRepository: $accessRegistryRepository,
            crypterDatabaseUserService: $crypterDatabaseUserService,
        );

        self::assertSame([], $service->getDecryptedCredentials([
            'publicId' => 'public-123',
            'domain' => 'target.example',
        ]));
    }

    public function testSetDecryptedUserIdentityWritesOneTouchStateToCache(): void
    {
        $authBridge = (new AuthBridge())
            ->setOneTouchProcessId('one-touch-123')
            ->setProcessState(false);

        $authBridgeRepository = $this->createMock(AuthBridgeRepository::class);
        $authBridgeRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['oneTouchProcessId' => 'one-touch-123'])
            ->willReturn($authBridge);

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::once())
            ->method('set')
            ->with(
                'one-touch-123',
                self::callback(static function (string $payload): bool {
                    $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    return $decoded['oneTouchProcessId'] === 'one-touch-123'
                        && $decoded['userIdentity']['publicId'] === 'public-123'
                        && $decoded['userIdentity']['email'] === 'user@example.test';
                }),
                300
            );

        $service = $this->createService(
            authBridgeRepository: $authBridgeRepository,
            processStateCacheService: $cacheService,
        );

        self::assertTrue($service->setDecryptedUserIdentity([
            'oneTouchProcessId' => 'one-touch-123',
            'publicId' => 'public-123',
            'email' => 'user@example.test',
        ]));
    }

    private function createService(
        ?AccessRegistryRepository $accessRegistryRepository = null,
        ?CrypterDatabaseAccessRegistryService $crypterDatabaseUserService = null,
        ?SodiumService $sodiumService = null,
        ?AuthBridgeRepository $authBridgeRepository = null,
        ?CrypterDatabaseLoginService $crypterDatabaseLoginService = null,
        ?LoginDatabaseService $loginDatabaseService = null,
        ?LoggerInterface $logger = null,
        ?ApplicationEncryptor $applicationEncryptor = null,
        ?FirebaseService $firebaseService = null,
        ?ProcessStateCacheService $processStateCacheService = null,
    ): Encryptor {
        return new Encryptor(
            $accessRegistryRepository ?? $this->createMock(AccessRegistryRepository::class),
            $crypterDatabaseUserService ?? new CrypterDatabaseAccessRegistryService(
                $this->createParameterBag(),
                $this->createMock(LoggerInterface::class),
            ),
            $sodiumService ?? $this->createMock(SodiumService::class),
            $authBridgeRepository ?? $this->createMock(AuthBridgeRepository::class),
            $crypterDatabaseLoginService ?? $this->createMock(CrypterDatabaseLoginService::class),
            $loginDatabaseService ?? $this->createMock(LoginDatabaseService::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $applicationEncryptor ?? $this->createMock(ApplicationEncryptor::class),
            $firebaseService ?? $this->createMock(FirebaseService::class),
            $processStateCacheService ?? $this->createMock(ProcessStateCacheService::class),
        );
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
            ]);

        return $params;
    }
}
