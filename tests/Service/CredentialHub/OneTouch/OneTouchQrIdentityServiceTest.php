<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\OneTouch;

use App\Service\CredentialHub\Shared\SharedRegistrationService;
use App\DTO\CredentialHub\OneTouch\OneTouchQrIdentityRequestDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\OneTouchDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OneTouchQrIdentityServiceTest extends TestCase
{
    public function testHandleReturnsOneTouchProcessPayload(): void
    {
        $request = OneTouchQrIdentityRequestDTO::fromArray(['type' => 'one-touch']);
        $identity = new CredentialHubIdentityDTO();
        $identity->setCreatedAt(null);
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setXExtensionAuthTwo(null);
        $identity->setSecret(null);
        $identity->setIv(null);
        $identity->setOneTouchProcessId('process-1');
        $qrContent = new OneTouchDTO('process-1', 'auth-1', 'one-touch', 'extension', null, null);

        $sharedRegistrationService = $this->createMock(SharedRegistrationService::class);
        $sharedRegistrationService
            ->expects(self::once())
            ->method('getOneTouchQrContent')
            ->with(self::isInstanceOf(\stdClass::class), 'auth-1', 'process-1')
            ->willReturn($qrContent);

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('oneTouchProcessId')
            ->willReturn($identity);

        $qrService = $this->createMock(QrService::class);
        $qrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with($qrContent)
            ->willReturn('qr-code');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('critical');

        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($qrContent)
            ->willReturn(new ConstraintViolationList());

        $service = new OneTouchQrIdentityService($sharedRegistrationService, $authBridgeService, $qrService, $logger);
        $result = $service->handle($request, $validator);

        self::assertSame('process-1', $result['oneTouchProcessId']);
        self::assertSame('qr-code', $result['qrCode']);
    }
}
