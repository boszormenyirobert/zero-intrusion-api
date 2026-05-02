<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\VaultReadQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;

final class VaultReadQrIdentityServiceTest extends TestCase
{
    public function testHandleUsesNotificationServiceForUserNotification(): void
    {
        $request = new VaultReadQrIdentityRequestDTO('extension', 'applications', 'public-1', []);
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setApplicationProcessId('application-1');
        $qrContent = new VaultReadQrContentDTO('application-1', 'applications', 'extension', 'auth-1', 'iv-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('applicationProcessId')
            ->willReturn($identity);

        $vaultReadService = $this->createMock(VaultReadService::class);
        $vaultReadService
            ->expects(self::once())
            ->method('getQrContent')
            ->with('applications', 'extension', 'auth-1', $identity)
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
            ->with('vaultRead', 'public-1', $qrContent);

        $service = new VaultReadQrIdentityService(
            $authBridgeService,
            $qrService,
            $vaultReadService,
            $sharedNotificationService,
        );

        self::assertSame('application-1', $service->handle($request)['applicationProcessId']);
    }
}