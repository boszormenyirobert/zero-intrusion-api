<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedRequestIdentityService;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteQrIdentityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultDeleteQrIdentityServiceTest extends TestCase
{
    public function testHandleUsesSpecificSharedCollaborators(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/qr-identity', 'POST');
        $process = [
            'userPublicId' => 'public-1',
            'targetId' => 'target-1',
            'type' => 'vault-delete',
            'source' => 'extension',
        ];
        $identity = [
            'toQrRead' => ['removeProcessId' => 'remove-1', 'qrCode' => 'qr-code'],
            'toNotification' => (object) ['targetId' => 'target-1'],
        ];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, PayloadKeys::VAULT_DELETE_QR_IDENTITY, true)
            ->willReturn($process);

        $sharedRequestIdentityService = $this->createMock(SharedRequestIdentityService::class);
        $sharedRequestIdentityService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with($process, PayloadKeys::VAULT_DELETE_PROCESS_ID)
            ->willReturn($identity);

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with('vaultDelete', 'public-1', $identity['toNotification']);

        $service = new VaultDeleteQrIdentityService(
            $sharedPayloadService,
            $sharedRequestIdentityService,
            $sharedNotificationService,
        );

        self::assertSame($identity['toQrRead'], $service->handle($request));
    }
}