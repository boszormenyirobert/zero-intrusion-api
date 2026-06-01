<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Read;

use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\Shared\ReadCredentialCacheResolver;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class VaultReadCredentialDecryptedServiceTest extends TestCase
{
    public function testHandleReturnsCachedCredentialsWhenAvailable(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/credential/decrypted', 'POST');
        $user = ['publicId' => 'public-1'];
        $cached = ['credentials' => [['application' => 'vault-app']], 'validation' => true];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getPayload')
            ->with($request, 'vault_read_credential_encrypted')
            ->willReturn($user);

        $cacheResolver = $this->createMock(ReadCredentialCacheResolver::class);
        $cacheResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($user)
            ->willReturn($cached);

        $processStateCacheService = $this->createMock(ProcessStateCacheService::class);
        $processStateCacheService->expects(self::never())->method('get');

        $accessRegistryRepository = $this->createMock(AccessRegistryRepository::class);
        $accessRegistryRepository->expects(self::never())->method('findBy');

        $crypterDatabaseAccessRegistryService = (new \ReflectionClass(CrypterDatabaseAccessRegistryService::class))
            ->newInstanceWithoutConstructor();

        $logger = $this->createMock(LoggerInterface::class);

        $service = new VaultReadCredentialDecryptedService(
            $sharedPayloadService,
            $processStateCacheService,
            $accessRegistryRepository,
            $crypterDatabaseAccessRegistryService,
            $cacheResolver,
            $logger,
        );

        self::assertSame($cached, $service->handle($request));
    }
}