<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubHandler;

use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteApplication;
use App\Service\AccessRegistry\CredentialHubResolver\CheckService;
use App\Service\AccessRegistry\CredentialHubResolver\DecryptService;
use App\Service\AccessRegistry\CredentialHubResolver\DeleteService;
use App\Service\AccessRegistry\CredentialHubResolver\FilterService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AccessRegistry\CredentialHubResolver\WriteService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AccessRegistry\DTO\DeleteApplicationDto;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class DeleteApplicationTest extends TestCase
{
    public function testDeleteApplicationReturnsSuccessWhenRegistryEntryRemoved(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn(new \App\Entity\AccessRegistry());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove');
        $entityManager->expects(self::once())->method('flush');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('updateProcessState')
            ->with('removeProcessId', 'remove-123');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('set')
            ->with(
                'remove-123',
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

        $service = $this->createService(
            repository: $repository,
            entityManager: $entityManager,
            authBridgeService: $authBridgeService,
            cache: $cache,
        );

        self::assertSame(
            [
                'deletedFromRegistry' => '',
                'processState' => null,
            ],
            $service->deleteApplication(new DeleteApplicationDto('remove-123', 'target-1'))
        );
    }

    public function testDeleteApplicationReturnsMessageWhenRegistryEntryMissing(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('updateProcessState')
            ->with('removeProcessId', 'remove-123');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache->expects(self::once())->method('set');

        $service = $this->createService(
            repository: $repository,
            entityManager: $entityManager,
            authBridgeService: $authBridgeService,
            cache: $cache,
        );

        self::assertSame(
            [
                'deletedFromRegistry' => 'Application not found or already deleted',
                'processState' => null,
            ],
            $service->deleteApplication(new DeleteApplicationDto('remove-123', 'target-1'))
        );
    }

    private function createService(
        ?AccessRegistryRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?AuthBridgeService $authBridgeService = null,
        ?ProcessStateCacheService $cache = null,
    ): DeleteApplication {
        $repository ??= $this->createMock(AccessRegistryRepository::class);
        $entityManager ??= $this->createMock(EntityManagerInterface::class);
        $authBridgeService ??= $this->createMock(AuthBridgeService::class);
        $cache ??= $this->createMock(ProcessStateCacheService::class);

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

        $domainService = new AccessRegistryDomainService(
            $this->createMock(LoggerInterface::class),
            $authBridgeService,
            $resolver,
            $cache,
        );

        return new DeleteApplication(
            $domainService,
            $resolver,
            $authBridgeService,
            $cache,
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
