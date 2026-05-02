<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\Vault\Delete\VaultDeleteService;
use App\DTO\CredentialHub\Vault\Delete\VaultDeleteCredentialResultDTO;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteCredentialService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultDeleteCredentialServiceTest extends TestCase
{
    public function testHandleReturnsDeleteStateAndErrorMessageWhenRegistryDeletionFails(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/credential', 'POST');
        $process = ['removeProcessId' => 'process-1', 'targetId' => 'target-1'];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'vault_delete_credential', true)
            ->willReturn($process);

        $vaultDeleteService = $this->createMock(VaultDeleteService::class);
        $vaultDeleteService
            ->expects(self::once())
            ->method('deleteApplication')
            ->with($process)
            ->willReturn([
                'processState' => true,
                'deletedFromRegistry' => false,
            ]);

        $service = new VaultDeleteCredentialService($sharedPayloadService, $vaultDeleteService);

        self::assertEquals(
            new VaultDeleteCredentialResultDTO(true, 'Application not found or already deleted'),
            $service->handle($request)
        );
    }
}