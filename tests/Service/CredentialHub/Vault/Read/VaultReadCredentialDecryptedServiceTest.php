<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultReadCredentialDecryptedServiceTest extends TestCase
{
    public function testHandleReturnsDecryptedVaultCredentials(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/credential/decrypted', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getPayload')
            ->with($request, 'vault_read_credential_encrypted')
            ->willReturn(['publicId' => 'public-1']);

        $vaultReadService = $this->createMock(VaultReadService::class);
        $vaultReadService
            ->expects(self::once())
            ->method('getDecryptedCredentials')
            ->with('public-1')
            ->willReturn([['application' => 'vault-app']]);

        $service = new VaultReadCredentialDecryptedService($sharedPayloadService, $vaultReadService);

        self::assertSame(['credentials' => [['application' => 'vault-app']]], $service->handle($request));
    }
}