<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Delete;

use App\Service\CredentialHub\Domain\Delete\DomainDeleteService;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\DomainDeleteQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DomainDeleteQrIdentityServiceTest extends TestCase
{
    public function testHandleUsesNotificationServiceForUserNotification(): void
    {
        $request = new DomainDeleteQrIdentityRequestDTO('example.test', 'domain-delete', 'extension', 'target-1', 'public-1');
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setRemoveProcessId('remove-1');
        $qrContent = new DomainDeleteQrContentDTO('auth-1', 'example.test', 'domain', 'extension', 'target-1', 'remove-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('removeProcessId')
            ->willReturn($identity);

        $domainDeleteService = $this->createMock(DomainDeleteService::class);
        $domainDeleteService
            ->expects(self::once())
            ->method('getQrContent')
            ->with('example.test', 'domain-delete', 'extension', 'target-1', 'auth-1', 'remove-1')
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
            ->with('domainDelete', 'public-1', $qrContent);

        $service = new DomainDeleteQrIdentityService(
            $authBridgeService,
            $qrService,
            $domainDeleteService,
            $sharedNotificationService,
            $validator,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('remove-1', $service->handle($request)['removeProcessId']);
    }

    public function testHandleSkipsNotificationWhenUserPublicIdIsMissing(): void
    {
        $request = new DomainDeleteQrIdentityRequestDTO('example.test', 'domain-delete', 'extension', 'target-1', null);
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setRemoveProcessId('remove-1');
        $qrContent = new DomainDeleteQrContentDTO('auth-1', 'example.test', 'domain-delete', 'extension', 'target-1', 'remove-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('removeProcessId')
            ->willReturn($identity);

        $domainDeleteService = $this->createMock(DomainDeleteService::class);
        $domainDeleteService
            ->expects(self::once())
            ->method('getQrContent')
            ->with('example.test', 'domain-delete', 'extension', 'target-1', 'auth-1', 'remove-1')
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

        $service = new DomainDeleteQrIdentityService(
            $authBridgeService,
            $qrService,
            $domainDeleteService,
            $sharedNotificationService,
            $validator,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('remove-1', $service->handle($request)['removeProcessId']);
    }
}