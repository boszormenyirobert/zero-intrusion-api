<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry;

use App\Entity\AccessRegistry;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\AccessRegistryVaultService;
use App\Service\AccessRegistry\CredentialHubResolver\CheckService;
use App\Service\AccessRegistry\CredentialHubResolver\DecryptService;
use App\Service\AccessRegistry\CredentialHubResolver\DeleteService;
use App\Service\AccessRegistry\CredentialHubResolver\FilterService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AccessRegistry\CredentialHubResolver\WriteService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class AccessRegistryVaultServiceTest extends TestCase
{
    public function testEditApplicationAccessRegistryDeletesExistingTargetAndPersistsEncryptedApplication(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn(new AccessRegistry());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove');
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (AccessRegistry $registry): bool {
                return $registry->isRegistrationState() === true
                    && $registry->getPublicId() === 'public-1'
                    && $registry->getRegistrationProcessId() === 'process-1'
                    && $registry->getTargetId() === 'target-1'
                    && $registry->getApplication() !== 'mail'
                    && $registry->getUserCredential() !== 'secret-credential';
            }));
        $entityManager->expects(self::exactly(2))->method('flush');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('set')
            ->with(
                'process-1',
                self::callback(static function (string $payload): bool {
                    $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    return $decoded === [
                        'process' => true,
                        'validation' => true,
                        'process_check' => true,
                        'success' => true,
                    ];
                }),
                300
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('process-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService->expects(self::never())->method('updateProcessState');

        $service = $this->createService(
            repository: $repository,
            entityManager: $entityManager,
            cache: $cache,
            logger: $logger,
            authBridgeService: $authBridgeService,
        );

        $service->editApplicationAccessRegistry([
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'application' => 'mail',
            'userCredential' => 'secret-credential',
            'description' => 'Primary app',
        ]);

        self::assertTrue(true);
    }

    public function testEditApplicationAccessRegistryStillPersistsWhenTargetDoesNotExist(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache->expects(self::once())->method('set');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('process-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService->expects(self::never())->method('updateProcessState');

        $service = $this->createService(
            repository: $repository,
            entityManager: $entityManager,
            cache: $cache,
            logger: $logger,
            authBridgeService: $authBridgeService,
        );

        $service->editApplicationAccessRegistry([
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'application' => 'mail',
            'userCredential' => 'secret-credential',
            'description' => 'Primary app',
        ]);

        self::assertTrue(true);
    }

    private function createService(
        ?AccessRegistryRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?ProcessStateCacheService $cache = null,
        ?LoggerInterface $logger = null,
        ?AuthBridgeService $authBridgeService = null,
    ): AccessRegistryVaultService {
        $repository ??= $this->createMock(AccessRegistryRepository::class);
        $entityManager ??= $this->createMock(EntityManagerInterface::class);
        $cache ??= $this->createMock(ProcessStateCacheService::class);
        $logger ??= $this->createMock(LoggerInterface::class);
        $authBridgeService ??= $this->createMock(AuthBridgeService::class);

        $resolver = new ResolverService(
            new CheckService(),
            new DecryptService($this->createCrypter()),
            new FilterService($repository),
            new WriteService(
                $this->createCrypter(),
                $entityManager,
                $this->createMock(LoggerInterface::class),
                $this->createMock(Encryptor::class),
            ),
            new DeleteService(
                $repository,
                $entityManager,
                $this->createMock(LoggerInterface::class),
            ),
        );

        return new AccessRegistryVaultService(
            $entityManager,
            $this->createCrypter(),
            $logger,
            $cache,
            $resolver,
            $authBridgeService,
        );
    }

    private function createCrypter(): CrypterDatabaseAccessRegistryService
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->with('DATABASE_HASH_SECRET')
            ->willReturn('0123456789abcdef0123456789abcdef');

        return new CrypterDatabaseAccessRegistryService(
            $params,
            $this->createMock(LoggerInterface::class),
        );
    }
}
