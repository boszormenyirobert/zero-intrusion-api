<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry;

use App\DTO\CredentialHub\ResponseDTO;
use App\Repository\AccessRegistryRepository;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\AccessRegistry\CredentialHubHandler\CredentialHubHandler;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteApplication;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteDomain;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryRegistration;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryState;
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
use App\Service\Notifier\NotifierService;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AccessRegistryRegistrationServiceTest extends TestCase
{
    public function testAddAccessRegistryDelegatesToRegistrationHandler(): void
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
        $cache->expects(self::once())->method('set');

        $service = $this->createService(repository: $repository, entityManager: $entityManager, cache: $cache);
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
        self::assertArrayHasKey('targetId', $result);
    }

    public function testSetRegistrationStateDelegatesToStateHandler(): void
    {
        $service = $this->createService();

        self::assertSame(
            ['publicId' => 'public-1', 'registrationState' => true],
            $service->setRegistrationState(['publicId' => 'public-1'], true)
        );
    }

    public function testGetStateDelegatesToStateHandler(): void
    {
        $authBridgeRepository = $this->createMock(AuthBridgeRepository::class);
        $authBridgeRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['registrationProcessId' => 'process-1'])
            ->willReturn(null);

        $service = $this->createService(authBridgeRepository: $authBridgeRepository);
        $result = $service->getState('process-1', 'registrationProcessId');

        self::assertInstanceOf(ResponseDTO::class, $result);
        self::assertFalse($result->isProcess());
    }

    private function createService(
        ?AccessRegistryRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?ProcessStateCacheService $cache = null,
        ?AuthBridgeRepository $authBridgeRepository = null,
        ?NotifierService $notifier = null,
    ): AccessRegistryRegistrationService {
        $repository ??= $this->createMock(AccessRegistryRepository::class);
        $entityManager ??= $this->createMock(EntityManagerInterface::class);
        $cache ??= $this->createMock(ProcessStateCacheService::class);
        $authBridgeRepository ??= $this->createMock(AuthBridgeRepository::class);

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

        $handler = new CredentialHubHandler(
            new RegistryRegistration($this->createMock(LoggerInterface::class), $domainService),
            new DeleteDomain($domainService, $this->createMock(AuthBridgeService::class), $cache),
            new DeleteApplication($domainService, $resolver, $this->createMock(AuthBridgeService::class), $cache),
            new RegistryState($authBridgeRepository, $entityManager, $this->createMock(LoggerInterface::class)),
        );

        return new AccessRegistryRegistrationService(
            $handler,
            $notifier ?? new NotifierService(
                $this->createMock(LoggerInterface::class),
                $this->createMock(HttpClientInterface::class),
                $this->createMock(CorporateIdentityRepository::class),
                $this->createMock(IdentityRepository::class),
                $this->createMock(CrypterDatabaseIdentityService::class),
                $this->createMock(Encryptor::class),
                $this->createMock(ContainerBagInterface::class),
                $this->createMock(CrypterDatabaseService::class),
            ),
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
