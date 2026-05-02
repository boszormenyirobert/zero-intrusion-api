<?php

declare(strict_types=1);

namespace App\Tests\Controller\DeviceManagement\Restore;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\DeviceManagement\Restore\RestoreController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Device\Restore\ReplaceDevicePinRequestDTO;
use App\DTO\Device\Restore\ReplaceDeviceRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\Device\Restore\ReplaceDevicePinRequestMapper;
use App\Service\Device\Restore\ReplaceDevicePinService;
use App\Service\Device\Restore\ReplaceDeviceRequestMapper;
use App\Service\Device\Restore\ReplaceDeviceService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class RestoreControllerTest extends TestCase
{
    public function testReplaceDeviceRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(RestoreController::class, 'replaceDevice');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testReplaceDeviceHashRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(RestoreController::class, 'replaceDeviceHash');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testReplaceDeviceReturnsNotifications(): void
    {
        $request = Request::create('/api/device/replace', 'POST');
        $validatedPayload = ['replaceDevice' => ['email' => 'user@example.test', 'phone' => '+3612345678']];
        $dto = new ReplaceDeviceRequestDTO('user@example.test', '+3612345678');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'replaceDevice')
            ->willReturn($validatedPayload);

        $replaceMapper = $this->createMock(ReplaceDeviceRequestMapper::class);
        $replaceMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($dto);

        $replaceService = $this->createMock(ReplaceDeviceService::class);
        $replaceService
            ->expects(self::once())
            ->method('handle')
            ->with($dto)
            ->willReturn(['success' => true]);

        $pinMapper = $this->createMock(ReplaceDevicePinRequestMapper::class);
        $pinService = $this->createMock(ReplaceDevicePinService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new RestoreController($payloadValidator, $responseHelper, $replaceMapper, $replaceService, $pinMapper, $pinService);
        $response = $controller->replaceDevice($request);

        self::assertSame(['success' => true], json_decode((string) $response->getContent(), true));
    }

    public function testReplaceDeviceHashReturnsHandyIdentifier(): void
    {
        $request = Request::create('/api/device/replace/pin', 'POST');
        $validatedPayload = ['restorePin' => ['replaceHash' => 'hash-1', 'data' => ['pin' => '1234']]];
        $dto = new ReplaceDevicePinRequestDTO($validatedPayload);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'restorePin')
            ->willReturn($validatedPayload);

        $replaceMapper = $this->createMock(ReplaceDeviceRequestMapper::class);
        $replaceService = $this->createMock(ReplaceDeviceService::class);
        $pinMapper = $this->createMock(ReplaceDevicePinRequestMapper::class);
        $pinMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($dto);

        $pinService = $this->createMock(ReplaceDevicePinService::class);
        $pinService
            ->expects(self::once())
            ->method('handle')
            ->with($dto)
            ->willReturn(['publicId' => 'public-1']);

        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new RestoreController($payloadValidator, $responseHelper, $replaceMapper, $replaceService, $pinMapper, $pinService);
        $response = $controller->replaceDeviceHash($request);

        self::assertSame(['publicId' => 'public-1'], json_decode((string) $response->getContent(), true));
    }

    public function testReplaceDeviceUsesResponseHelperOnErrors(): void
    {
        $request = Request::create('/api/device/replace', 'POST');
        $exception = new \InvalidArgumentException('Invalid replace device payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'replaceDevice')
            ->willThrowException($exception);

        $replaceMapper = $this->createMock(ReplaceDeviceRequestMapper::class);
        $replaceService = $this->createMock(ReplaceDeviceService::class);
        $pinMapper = $this->createMock(ReplaceDevicePinRequestMapper::class);
        $pinService = $this->createMock(ReplaceDevicePinService::class);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new RestoreController($payloadValidator, $responseHelper, $replaceMapper, $replaceService, $pinMapper, $pinService);

        self::assertSame($errorResponse, $controller->replaceDevice($request));
    }
}
