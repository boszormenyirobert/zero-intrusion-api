<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\DTO\CredentialHub\Domain\Read\ExtensionCredentialRequestDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\DomainReadQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DomainReadQrIdentityServiceTest extends TestCase
{
    public function testHandleUsesNotificationServiceForUserNotification(): void
    {
        $request = new ExtensionCredentialRequestDTO('example.test', 'public-1');
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setDomainProcessId('process-1');
        $qrContent = new DomainReadQrContentDTO('example.test', 'process-1', 'auth-1', 'domain', 'extension', 'iv-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('domainProcessId')
            ->willReturn($identity);

        $domainReadService = $this->createMock(DomainReadService::class);
        $domainReadService
            ->expects(self::once())
            ->method('getQrContent')
            ->with('example.test', 'auth-1', $identity)
            ->willReturn($qrContent);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($qrContent)
            ->willReturn(new ConstraintViolationList());

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
            ->with('domainRead', 'public-1', $qrContent);

        $service = new DomainReadQrIdentityService(
            $authBridgeService,
            $qrService,
            $domainReadService,
            $sharedNotificationService,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('process-1', $service->handle($request, $validator)['domainProcessId']);
    }

    public function testHandleSkipsNotificationWhenUserPublicIdIsMissing(): void
    {
        $request = new ExtensionCredentialRequestDTO('example.test', null);
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setDomainProcessId('process-1');
        $qrContent = new DomainReadQrContentDTO('example.test', 'process-1', 'auth-1', 'domain', 'extension', 'iv-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('domainProcessId')
            ->willReturn($identity);

        $domainReadService = $this->createMock(DomainReadService::class);
        $domainReadService
            ->expects(self::once())
            ->method('getQrContent')
            ->with('example.test', 'auth-1', $identity)
            ->willReturn($qrContent);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($qrContent)
            ->willReturn(new ConstraintViolationList());

        $qrService = $this->createMock(QrService::class);
        $qrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with($qrContent)
            ->willReturn('qr-code');

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService->expects(self::never())->method('sendFcmNotification');

        $service = new DomainReadQrIdentityService(
            $authBridgeService,
            $qrService,
            $domainReadService,
            $sharedNotificationService,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('process-1', $service->handle($request, $validator)['domainProcessId']);
    }
}