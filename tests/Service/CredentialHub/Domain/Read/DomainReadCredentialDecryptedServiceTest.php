<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Read;

use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialDecryptedService;
use App\Service\CredentialHub\Domain\Read\DomainService;
use App\Service\CredentialHub\Shared\ReadCredentialCacheResolver;
use App\Service\CredentialHub\SharedPayloadService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class DomainReadCredentialDecryptedServiceTest extends TestCase
{
    public function testHandleFallsBackToDatabaseWhenCacheMiss(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/credential/decrypted', 'POST');
        $user = [
            'qrCacheKey' => 'qr-cache-key',
            'publicId' => 'public-1',
            'domainProcessId' => 'process-1',
            'publicKey' => 'pk-1',
        ];

        $cachedQrData = [
            'domainProcessId' => 'process-1',
            'publicKey' => 'pk-1',
        ];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getPayload')
            ->with($request, 'domain_read_credential_encrypted')
            ->willReturn($user);

        $cacheResolver = $this->createMock(ReadCredentialCacheResolver::class);
        $cacheResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($user)
            ->willReturn(false);

        $processStateCacheService = $this->createMock(ProcessStateCacheService::class);
        $processStateCacheService
            ->expects(self::once())
            ->method('get')
            ->with('qr-cache-key')
            ->willReturn($cachedQrData);

        $domainService = $this->createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('getDecryptedCredentials')
            ->with([
                'domainProcessId' => 'process-1',
                'publicKey' => 'pk-1',
                'qrCacheKey' => 'qr-cache-key',
                'publicId' => 'public-1',
            ])
            ->willReturn(['credential' => 'secret']);

        $logger = $this->createMock(LoggerInterface::class);

        $service = new DomainReadCredentialDecryptedService(
            $sharedPayloadService,
            $domainService,
            $logger,
            $processStateCacheService,
            $cacheResolver,
        );

        self::assertSame([
            'credentials' => ['credential' => 'secret'],
            'domainProcessId' => 'process-1',
            'publicKey' => 'pk-1',
        ], $service->handle($request));
    }
}