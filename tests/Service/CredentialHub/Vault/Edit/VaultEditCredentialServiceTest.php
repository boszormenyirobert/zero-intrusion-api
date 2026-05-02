<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Edit;

use App\DTO\CredentialHub\Vault\Edit\VaultEditCredentialResultDTO;
use App\Service\AccessRegistry\AccessRegistryVaultService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\Vault\Edit\VaultEditCredentialService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultEditCredentialServiceTest extends TestCase
{
    public function testHandleReturnsEditedVaultProcess(): void
    {
        $request = Request::create('/api/credential-hub/vault/edit/credential', 'POST');
        $process = ['registrationProcessId' => 'process-1'];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'vault_edit_credential', true)
            ->willReturn($process);

        $accessRegistryVaultService = $this->createMock(AccessRegistryVaultService::class);
        $accessRegistryVaultService
            ->expects(self::once())
            ->method('editApplicationAccessRegistry')
            ->with($process);

        $service = new VaultEditCredentialService($sharedPayloadService, $accessRegistryVaultService);

        self::assertEquals(
            new VaultEditCredentialResultDTO(null, ''),
            $service->handle($request)
        );
    }
}