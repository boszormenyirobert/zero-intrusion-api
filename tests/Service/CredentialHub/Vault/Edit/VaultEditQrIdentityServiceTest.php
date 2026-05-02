<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Edit;

use App\Controller\CredentialHub\Shared\SharedRegistrationService;
use App\Controller\CredentialHub\Vault\Edit\VaultEditService;
use App\DTO\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\VaultEditQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;

final class VaultEditQrIdentityServiceTest extends TestCase
{
    public function testHandleUsesNotificationServiceForUserNotification(): void
    {
        $request = new VaultEditQrIdentityRequestDTO('public-1', ['targetId' => 'target-1']);
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setRegistrationProcessId('registration-1');
        $qrContent = new VaultEditQrContentDTO('extension', 'target-1', 'application', 'auth-1', 'registration-1', 'vault-app');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('registrationProcessId')
            ->willReturn($identity);

        $sharedRegistrationService = $this->createMock(SharedRegistrationService::class);
        $sharedRegistrationService
            ->expects(self::once())
            ->method('saveUserCredentialInAuthBridge')
            ->with(self::callback(static fn (mixed $value): bool => is_object($value)), 'registration-1');

        $vaultEditService = $this->createMock(VaultEditService::class);
        $vaultEditService
            ->expects(self::once())
            ->method('getQrContent')
            ->with(self::callback(static fn (mixed $value): bool => is_object($value)), 'auth-1', 'registration-1')
            ->willReturn($qrContent);

        $qrService = $this->createMock(QrService::class);
        $qrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with($qrContent)
            ->willReturn('qr-code');

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with('vaultEdit', 'public-1', $qrContent);

        $service = new VaultEditQrIdentityService(
            $authBridgeService,
            $qrService,
            $vaultEditService,
            $sharedNotificationService,
            $sharedRegistrationService,
        );

        self::assertSame('registration-1', $service->handle($request)['registrationProcessId']);
    }
}