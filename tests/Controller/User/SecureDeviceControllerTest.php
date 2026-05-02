<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Controller\User\SecureDeviceController;
use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\DTO\User\Qr\QrIdentityResultDTO;
use App\Helper\ResponseHelper;
use App\Service\User\Qr\QrIdentityService;
use App\Service\User\SecureDevice\SecureDeviceQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class SecureDeviceControllerTest extends TestCase
{
    public function testSecureDeviceQrIdentityRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(SecureDeviceController::class, 'secureDeviceQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testSecureDeviceQrIdentityReturnsGeneratedResponse(): void
    {
        $request = Request::create('/api/user/secure-device/qr-identity', 'POST');
        $validatedPayload = [
            'secure_device_registration' => [
                'corporatePublicId' => 'corp-1',
            ],
        ];
        $dto = new QrIdentityRequestDTO($validatedPayload['secure_device_registration'], 'domainProcessId');
        $result = new QrIdentityResultDTO('encrypted-body', ['X-Test' => 'header']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'secure_device_registration')
            ->willReturn($validatedPayload);

        $mapper = $this->createMock(SecureDeviceQrIdentityRequestMapper::class);
        $mapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($dto);

        $service = $this->createMock(QrIdentityService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->with($dto)
            ->willReturn($result);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::never())->method('handleException');

        $controller = new SecureDeviceController($payloadValidator, $mapper, $service);
        $response = $controller->secureDeviceQrIdentity($request, $responseHelper);

        self::assertSame('encrypted-body', $response->getContent());
        self::assertSame('header', $response->headers->get('X-Test'));
    }

    public function testSecureDeviceQrIdentityUsesResponseHelperOnErrors(): void
    {
        $request = Request::create('/api/user/secure-device/qr-identity', 'POST');
        $exception = new \InvalidArgumentException('Invalid secure device registration payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'secure_device_registration')
            ->willThrowException($exception);

        $mapper = $this->createMock(SecureDeviceQrIdentityRequestMapper::class);
        $mapper->expects(self::never())->method('map');
        $service = $this->createMock(QrIdentityService::class);
        $service->expects(self::never())->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new SecureDeviceController($payloadValidator, $mapper, $service);

        self::assertSame($errorResponse, $controller->secureDeviceQrIdentity($request, $responseHelper));
    }
}
