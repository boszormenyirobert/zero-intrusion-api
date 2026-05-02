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

final class EncryptorAdditionalTest extends TestCase
{
    public function testGetDecryptedCredentialsReturnsFormattedCollectionForMatchingDomain(): void
    {
        $crypter = $this->createCrypter();
        $page = $crypter->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'reg-1',
            'targetId' => 'target-1',
            'domain' => 'example.test',
            'userCredential' => 'credential-value',
            'description' => 'Example domain',
        ], 'domain');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository->expects(self::once())->method('findBy')->with(['publicId' => 'public-1'])->willReturn([$page]);

        $service = $this->createService(accessRegistryRepository: $repository, crypterDatabaseUserService: $crypter);

        self::assertSame([[
            'credential' => 'credential-value',
            'description' => 'Example domain',
            'targetId' => 'target-1',
        ]], $service->getDecryptedCredentials(['publicId' => 'public-1', 'domain' => 'example.test']));
    }

    public function testFindDecryptedCredentialForWebReturnsExpectedPayloadAndNullWhenMissing(): void
    {
        $crypter = $this->createCrypter();
        $page = $crypter->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'reg-1',
            'targetId' => 'target-1',
            'corporateId' => 'corp-1',
            'domain' => 'example.test',
            'userCredential' => 'credential-value',
        ], 'domain');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('findBy')
            ->willReturnOnConsecutiveCalls([$page], [$page]);

        $sodium = $this->createMock(SodiumService::class);
        $sodium->expects(self::once())->method('sodiumDecrypt')->with('credential-value', 'user-secret')->willReturn('clear-text');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('critical');

        $service = $this->createService(
            accessRegistryRepository: $repository,
            crypterDatabaseUserService: $crypter,
            sodiumService: $sodium,
            logger: $logger,
        );

        self::assertSame(['decrypted' => 'clear-text'], $service->findDecryptedCredentialForWeb([
            'publicId' => 'public-1',
            'corporateId' => 'corp-1',
            'domain' => 'example.test',
        ], 'user-secret'));

        self::assertNull($service->findDecryptedCredentialForWeb([
            'publicId' => 'public-1',
            'corporateId' => 'corp-1',
            'domain' => 'missing.test',
        ], 'user-secret'));
    }

    public function testSetDecryptedValuesForDomainWritesEncryptedCredentialsToCache(): void
    {
        $authBridge = (new AuthBridge())
            ->setDomainProcessId('domain-process-1')
            ->setIv(base64_encode(random_bytes(16)));

        $authBridgeRepository = $this->createMock(AuthBridgeRepository::class);
        $authBridgeRepository->expects(self::once())->method('findOneBy')->with(['domainProcessId' => 'domain-process-1'])->willReturn($authBridge);

        $applicationEncryptor = $this->createMock(ApplicationEncryptor::class);
        $applicationEncryptor
            ->expects(self::once())
            ->method('encrypt')
            ->with(self::callback(static fn (array $list): bool => count($list) === 1 && $list[0] instanceof AccessRegistry), self::isType('string'))
            ->willReturn('encrypted-application');

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::once())
            ->method('set')
            ->with('domain-process-1', self::isType('string'), 300);

        $service = $this->createService(
            authBridgeRepository: $authBridgeRepository,
            applicationEncryptor: $applicationEncryptor,
            processStateCacheService: $cacheService,
        );

        self::assertTrue($service->setDecryptedValuesForDomain([
            'domainProcessId' => 'domain-process-1',
            'credentials' => [[
                'credential' => 'clear-text',
                'description' => 'Example domain',
                'targetId' => 'target-1',
            ]],
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
            $crypterDatabaseUserService ?? $this->createCrypter(),
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

    private function createCrypter(): CrypterDatabaseAccessRegistryService
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
        ]);

        return new CrypterDatabaseAccessRegistryService($params, $this->createMock(LoggerInterface::class));
    }
}