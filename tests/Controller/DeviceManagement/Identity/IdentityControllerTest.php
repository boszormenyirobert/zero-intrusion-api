<?php

declare(strict_types=1);

namespace App\Tests\Controller\DeviceManagement\Identity;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\DeviceManagement\Identity\IdentityController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Device\Identity\RecoverySettingsRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\Device\Identity\FirstSecretService;
use App\Service\Device\Identity\RecoverySettingsRequestMapper;
use App\Service\Device\Identity\RecoverySettingsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class IdentityControllerTest extends TestCase
{
    public function testCreateSecretRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(IdentityController::class, 'createSecret');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testSetRecoveryDataRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(IdentityController::class, 'setRecoveryData');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testCreateSecretReturnsGeneratedIdentityPayload(): void
    {
        $request = Request::create('/api/secret/new', 'POST');
        $keys = ['privateSecret' => ['publicId' => 'public-1']];

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'firstSecret')
            ->willReturn(['firstSecret' => 'no-data']);

        $firstSecretService = $this->createMock(FirstSecretService::class);
        $firstSecretService
            ->expects(self::once())
            ->method('handle')
            ->willReturn($keys);

        $recoveryMapper = $this->createMock(RecoverySettingsRequestMapper::class);
        $recoveryService = $this->createMock(RecoverySettingsService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::never())->method('handleException');

        $controller = new IdentityController($payloadValidator, $responseHelper, $firstSecretService, $recoveryMapper, $recoveryService);
        $response = $controller->createSecret($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($keys, json_decode((string) $response->getContent(), true));
    }

    public function testSetRecoveryDataReturnsSuccessState(): void
    {
        $request = Request::create('/api/secret/recovery-settings', 'POST');
        $validatedPayload = ['recoverySettings' => ['publicId' => 'public-1']];
        $dto = RecoverySettingsRequestDTO::fromArray([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'email' => 'user@example.test',
            'phone' => '+3612345678',
            'privacyPolicy' => true,
            'fcmToken' => 'token-1',
        ]);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'recoverySettings')
            ->willReturn($validatedPayload);

        $firstSecretService = $this->createMock(FirstSecretService::class);
        $recoveryMapper = $this->createMock(RecoverySettingsRequestMapper::class);
        $recoveryMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($dto);

        $recoveryService = $this->createMock(RecoverySettingsService::class);
        $recoveryService
            ->expects(self::once())
            ->method('handle')
            ->with($dto)
            ->willReturn(['success' => true]);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $controller = new IdentityController($payloadValidator, $responseHelper, $firstSecretService, $recoveryMapper, $recoveryService);
        $response = $controller->setRecoveryData($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['success' => true], json_decode((string) $response->getContent(), true));
    }

    public function testSetRecoveryDataUsesResponseHelperOnErrors(): void
    {
        $request = Request::create('/api/secret/recovery-settings', 'POST');
        $exception = new \InvalidArgumentException('Invalid recovery settings payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'recoverySettings')
            ->willThrowException($exception);

        $firstSecretService = $this->createMock(FirstSecretService::class);
        $recoveryMapper = $this->createMock(RecoverySettingsRequestMapper::class);
        $recoveryMapper->expects(self::never())->method('map');
        $recoveryService = $this->createMock(RecoverySettingsService::class);
        $recoveryService->expects(self::never())->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new IdentityController($payloadValidator, $responseHelper, $firstSecretService, $recoveryMapper, $recoveryService);

        self::assertSame($errorResponse, $controller->setRecoveryData($request));
    }
}
