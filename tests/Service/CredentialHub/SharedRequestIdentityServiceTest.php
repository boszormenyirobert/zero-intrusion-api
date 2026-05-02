<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\VaultDeleteQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedRequestIdentityService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;

final class SharedRequestIdentityServiceTest extends TestCase
{
    public function testGenerateRequestIdentityBuildsRemoveProcessPayloadForVaultDelete(): void
    {
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2025-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setRemoveProcessId('remove-123');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with(PayloadKeys::VAULT_DELETE_PROCESS_ID)
            ->willReturn($identity);

        $qrService = $this->createMock(QrService::class);
        $qrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with(self::isInstanceOf(VaultDeleteQrContentDTO::class))
            ->willReturn('qr-code');

        $service = new SharedRequestIdentityService($authBridgeService, $qrService);
        $result = $service->generateRequestIdentity([
            'source' => 'extension',
            'targetId' => 'target-1',
            'type' => 'vault-delete',
        ], PayloadKeys::VAULT_DELETE_PROCESS_ID);

        self::assertSame('remove-123', $result['toQrRead']['removeProcessId']);
        self::assertSame('qr-code', $result['toQrRead']['qrCode']);
        self::assertInstanceOf(VaultDeleteQrContentDTO::class, $result['toNotification']);
    }
}