<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubHandler;

use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryRegistration;
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

final class RegistryRegistrationTest extends TestCase
{
    public function testAddAccessRegistryCreatesNewTargetIdForNewRegistration(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('set')
            ->with(
                'process-1',
                self::callback(static function (string $payload): bool {
                    $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    return $decoded['process'] === true
                        && $decoded['validation'] === true
                        && $decoded['process_check'] === true
                        && $decoded['success'] === true;
                }),
                300
            );

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('Value of update: new');

        $service = $this->createService(
            registryLogger: $logger,
            repository: $repository,
            entityManager: $entityManager,
            cache: $cache,
        );

        $result = $service->addAccessRegistry([
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
            'update' => 'new',
        ], 'domain', false);

        self::assertSame('public-1', $result['publicId']);
        self::assertSame('process-1', $result['registrationProcessId']);
        self::assertSame('example.com', $result['domain']);
        self::assertSame('new', $result['update']);
        self::assertArrayHasKey('targetId', $result);
        self::assertSame(50, strlen($result['targetId']));
        self::assertArrayHasKey('encryptedAuthId', $result);
        self::assertNotSame('secret-credential', $result['encryptedAuthId']);
    }

    public function testAddAccessRegistryKeepsExistingTargetIdForUpdateFlow(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache->expects(self::once())->method('set');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('Value of update: update');

        $service = $this->createService(
            registryLogger: $logger,
            repository: $repository,
            entityManager: $entityManager,
            cache: $cache,
        );

        $result = $service->addAccessRegistry([
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'existing-target-id',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
            'update' => 'update',
        ], 'domain', false);

        self::assertSame('existing-target-id', $result['targetId']);
        self::assertArrayHasKey('encryptedAuthId', $result);
        self::assertNotSame('secret-credential', $result['encryptedAuthId']);
    }

    private function createService(
        ?LoggerInterface $registryLogger = null,
        ?AccessRegistryRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?ProcessStateCacheService $cache = null,
    ): RegistryRegistration {
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

        return new RegistryRegistration(
            $registryLogger ?? $this->createMock(LoggerInterface::class),
            $domainService,
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
