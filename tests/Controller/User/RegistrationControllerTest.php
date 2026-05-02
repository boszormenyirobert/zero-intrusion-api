<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Controller\User\RegistrationController;
use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\DTO\User\Qr\QrIdentityResultDTO;
use App\Helper\ResponseHelper;
use App\Service\User\Qr\QrIdentityService;
use App\Service\User\Registration\RegistrationQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class RegistrationControllerTest extends TestCase
{
    public function testRegistrationQrIdentityRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(RegistrationController::class, 'registrationQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testRegistrationQrIdentityReturnsGeneratedResponse(): void
    {
        $request = Request::create('/api/user/registration/qr-identity', 'POST');
        $validatedPayload = [
            'user_registration' => [
                'corporatePublicId' => 'corp-1',
            ],
        ];
        $dto = new QrIdentityRequestDTO($validatedPayload['user_registration'], 'registrationProcessId');
        $result = new QrIdentityResultDTO('encrypted-body', ['X-Test' => 'header']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'user_registration')
            ->willReturn($validatedPayload);

        $mapper = $this->createMock(RegistrationQrIdentityRequestMapper::class);
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

        $controller = new RegistrationController($payloadValidator, $mapper, $service);
        $response = $controller->registrationQrIdentity($request, $responseHelper);

        self::assertSame('encrypted-body', $response->getContent());
        self::assertSame('header', $response->headers->get('X-Test'));
    }

    public function testRegistrationQrIdentityUsesResponseHelperOnErrors(): void
    {
        $request = Request::create('/api/user/registration/qr-identity', 'POST');
        $exception = new \InvalidArgumentException('Invalid user registration payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'user_registration')
            ->willThrowException($exception);

        $mapper = $this->createMock(RegistrationQrIdentityRequestMapper::class);
        $mapper->expects(self::never())->method('map');
        $service = $this->createMock(QrIdentityService::class);
        $service->expects(self::never())->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new RegistrationController($payloadValidator, $mapper, $service);

        self::assertSame($errorResponse, $controller->registrationQrIdentity($request, $responseHelper));
    }
}
