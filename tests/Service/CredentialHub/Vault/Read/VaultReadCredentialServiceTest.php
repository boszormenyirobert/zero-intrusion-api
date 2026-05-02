<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Read;

use App\DTO\CredentialHub\Vault\Read\VaultReadCredentialResultDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultReadCredentialServiceTest extends TestCase
{
    public function testHandleReturnsPersistedVaultReadProcess(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/credential', 'POST');
        $process = ['applicationProcessId' => 'process-1'];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'vault_read_credential', true)
            ->willReturn($process);

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('persistDecryptedUserData')
            ->with($process)
            ->willReturn(true);

        $service = new VaultReadCredentialService($sharedPayloadService, $authBridgeService);

        self::assertEquals(
            new VaultReadCredentialResultDTO(true, ''),
            $service->handle($request)
        );
    }
}