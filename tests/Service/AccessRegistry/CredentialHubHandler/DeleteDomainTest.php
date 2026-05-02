<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubHandler;

use App\Entity\AccessRegistry;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteDomain;
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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class DeleteDomainTest extends TestCase
{
    public function testHandleDomainDeletionCachesSuccessWhenNoMatchingRegistrationExists(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([]);
        $repository->expects(self::never())->method('findOneBy');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

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
            cache: $cache,
        );

        self::assertNull($service->handleDomainDeletion([
            'publicId' => 'public-1',
            'domain' => 'example.com',
            'targetId' => 'target-1',
            'removeProcessId' => 'remove-123',
        ]));
    }

    public function testHandleDomainDeletionDeletesMatchingRegistrationAndCachesSuccess(): void
    {
        $crypter = $this->createCrypter();
        $encrypted = $crypter->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
        ], 'domain');

        $stored = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setDomain($encrypted->getDomain())
            ->setTargetId('target-1');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([$encrypted]);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'domain' => $encrypted->getDomain(),
                'publicId' => 'public-1',
                'targetId' => 'target-1',
            ])
            ->willReturn($stored);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($stored);
        $entityManager->expects(self::once())->method('flush');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache->expects(self::once())->method('set');

        $service = $this->createService(
            repository: $repository,
            entityManager: $entityManager,
            cache: $cache,
        );

        self::assertNull($service->handleDomainDeletion([
            'publicId' => 'public-1',
            'domain' => 'example.com',
            'targetId' => 'target-1',
            'removeProcessId' => 'remove-123',
        ]));
    }

    private function createService(
        ?AccessRegistryRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?ProcessStateCacheService $cache = null,
    ): DeleteDomain {
        $repository ??= $this->createMock(AccessRegistryRepository::class);
        $entityManager ??= $this->createMock(EntityManagerInterface::class);
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
            $this->createMock(AuthBridgeService::class),
            $resolver,
            $cache,
        );

        return new DeleteDomain(
            $domainService,
            $this->createMock(AuthBridgeService::class),
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
