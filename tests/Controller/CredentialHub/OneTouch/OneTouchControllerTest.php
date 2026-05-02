<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\OneTouch;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\OneTouch\OneTouchController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\OneTouch\OneTouchIdentifierResultDTO;
use App\DTO\CredentialHub\OneTouch\OneTouchQrIdentityRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\OneTouch\OneTouchIdentifierService;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityRequestMapper;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityService;
use App\Service\CredentialHub\OneTouch\OneTouchStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OneTouchControllerTest extends TestCase
{
    public function testOneTouchControllerRemovesHistoricalComments(): void
    {
        $reflection = new \ReflectionClass(OneTouchController::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('Class responsibility: mark the user the Desktop as "secure" machine for "oneTouchLogin"', $source);
        self::assertStringNotContainsString('Get user PublicId and Email', $source);
        self::assertStringNotContainsString('@param Request', $source);
    }

    public function testOneTouchQrIdentityRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(OneTouchController::class, 'oneTouchQrIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testOneTouchIdentifierRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(OneTouchController::class, 'oneTouchIdentifier');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(MobileHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testOneTouchStateRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(OneTouchController::class, 'oneTouchState');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(ExtensionHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testOneTouchQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/one-touch/qr-identity', 'POST');
        $validatedPayload = ['one_touch_qr_identity' => ['type' => 'one-touch']];
        $dto = OneTouchQrIdentityRequestDTO::fromArray(['type' => 'one-touch']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'one_touch_qr_identity')
            ->willReturn($validatedPayload);

        $mapper = $this->createMock(OneTouchQrIdentityRequestMapper::class);
        $mapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($dto);

        $service = $this->createMock(OneTouchQrIdentityService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->with($dto, self::isInstanceOf(ValidatorInterface::class))
            ->willReturn(['oneTouchProcessId' => 'process-1']);

        $identifierService = $this->createMock(OneTouchIdentifierService::class);
        $stateService = $this->createMock(OneTouchStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('createSuccessResponse')
            ->with(['oneTouchProcessId' => 'process-1'])
            ->willReturn(new JsonResponse(['oneTouchProcessId' => 'process-1']));

        $controller = new OneTouchController($payloadValidator, $responseHelper, $mapper, $service, $identifierService, $stateService);
        $response = $controller->oneTouchQrIdentity($request, $this->createMock(ValidatorInterface::class));

        self::assertSame(['oneTouchProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testOneTouchIdentifierReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/one-touch/identifier', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(OneTouchQrIdentityRequestMapper::class);
        $service = $this->createMock(OneTouchQrIdentityService::class);
        $identifierService = $this->createMock(OneTouchIdentifierService::class);
        $identifierService
            ->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn(new OneTouchIdentifierResultDTO(true, ''));

        $stateService = $this->createMock(OneTouchStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new OneTouchController($payloadValidator, $responseHelper, $mapper, $service, $identifierService, $stateService);
        $response = $controller->oneTouchIdentifier($request);

        self::assertSame(['one_touch_process' => true, 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testOneTouchStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/one-touch/state', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(OneTouchQrIdentityRequestMapper::class);
        $service = $this->createMock(OneTouchQrIdentityService::class);
        $identifierService = $this->createMock(OneTouchIdentifierService::class);
        $stateService = $this->createMock(OneTouchStateService::class);
        $stateService
            ->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn(['success' => true]);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('createSuccessResponse')
            ->with(['success' => true])
            ->willReturn(new JsonResponse(['success' => true]));

        $controller = new OneTouchController($payloadValidator, $responseHelper, $mapper, $service, $identifierService, $stateService);
        $response = $controller->oneTouchState($request);

        self::assertSame(['success' => true], json_decode((string) $response->getContent(), true));
    }
}
