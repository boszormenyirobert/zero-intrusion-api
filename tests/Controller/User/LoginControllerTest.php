<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\User\LoginController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\User\Login\LoginQrIdentityRequestDTO;
use App\DTO\User\Login\LoginQrIdentityResultDTO;
use App\Helper\ResponseHelper;
use App\Service\User\Login\LoginQrIdentityRequestMapper;
use App\Service\User\Login\LoginQrIdentityService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class LoginControllerTest extends TestCase
{
    public function testLoginQrIdentityRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(LoginController::class, 'loginQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testLoginQrIdentityReturnsGeneratedResponse(): void
    {
        $request = Request::create('/api/user/login/qr-identity', 'POST');
        $validatedPayload = [
            'user_login' => [
                'corporatePublicId' => 'corp-1',
                'corporateAuthentication' => 'signature',
                'domain' => 'https://example.test',
                'userPublicId' => 'user-1',
            ],
        ];
        $loginRequest = LoginQrIdentityRequestDTO::fromArray($validatedPayload['user_login']);
        $result = new LoginQrIdentityResultDTO('encrypted-body', ['X-Test' => 'header'], (object) ['domainProcessId' => 'process-123']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'user_login')
            ->willReturn($validatedPayload);

        $requestMapper = $this->createMock(LoginQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($loginRequest);

        $loginQrIdentityService = $this->createMock(LoginQrIdentityService::class);
        $loginQrIdentityService
            ->expects(self::once())
            ->method('handle')
            ->with($loginRequest)
            ->willReturn($result);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::never())
            ->method('handleException');

        $controller = new LoginController($payloadValidator, $requestMapper, $loginQrIdentityService);
        $response = $controller->loginQrIdentity($request, $responseHelper);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('encrypted-body', $response->getContent());
        self::assertSame('header', $response->headers->get('X-Test'));
    }

    public function testLoginQrIdentityUsesResponseHelperOnValidationErrors(): void
    {
        $request = Request::create('/api/user/login/qr-identity', 'POST');
        $exception = new \InvalidArgumentException('Invalid user login payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'user_login')
            ->willThrowException($exception);

        $requestMapper = $this->createMock(LoginQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::never())
            ->method('map');

        $loginQrIdentityService = $this->createMock(LoginQrIdentityService::class);
        $loginQrIdentityService
            ->expects(self::never())
            ->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new LoginController($payloadValidator, $requestMapper, $loginQrIdentityService);

        self::assertSame($errorResponse, $controller->loginQrIdentity($request, $responseHelper));
    }
}
