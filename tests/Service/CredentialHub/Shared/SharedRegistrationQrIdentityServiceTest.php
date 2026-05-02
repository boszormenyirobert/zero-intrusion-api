<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Shared;

use App\Controller\CredentialHub\Shared\SharedRegistrationService;
use App\DTO\CredentialHub\Shared\SharedRegistrationQrIdentityRequestDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\SharedRegistrationQrDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SharedRegistrationQrIdentityServiceTest extends TestCase
{
    public function testHandleUsesNotificationServiceForUserNotification(): void
    {
        $request = new SharedRegistrationQrIdentityRequestDTO('application', 'public-1', ['type' => 'application']);
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt('2026-01-01T00:00:00+00:00');
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo('auth-2');
        $identity->setSecret('secret');
        $identity->setIv('iv');
        $identity->setRegistrationProcessId('registration-1');
        $qrContent = new SharedRegistrationQrDTO('registration-1', 'auth-1', 'application', 'extension', 'new', 'public-1', 'target-1');
        $extendedQrContent = new SharedRegistrationQrDTO('registration-1', 'auth-1', 'application', 'extension', 'new', 'public-1', 'target-1');

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
        $sharedRegistrationService
            ->expects(self::once())
            ->method('getQrContent')
            ->with(self::callback(static fn (mixed $value): bool => is_object($value)), 'auth-1', 'registration-1')
            ->willReturn($qrContent);
        $sharedRegistrationService
            ->expects(self::once())
            ->method('getExtendedQrContent')
            ->with('application', $qrContent, self::callback(static fn (mixed $value): bool => is_object($value)))
            ->willReturn($extendedQrContent);

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
            ->with($extendedQrContent)
            ->willReturn('qr-code');

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with('sharedRegistration', 'public-1', $qrContent);

        $service = new SharedRegistrationQrIdentityService(
            $sharedRegistrationService,
            $sharedNotificationService,
            $authBridgeService,
            $qrService,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame('registration-1', $service->handle($request, $validator)['registrationProcessId']);
    }
}