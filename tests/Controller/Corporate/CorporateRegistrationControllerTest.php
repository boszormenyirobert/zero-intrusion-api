<?php

declare(strict_types=1);

namespace App\Tests\Controller\Corporate;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\Corporate\CorporateRegistrationController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Corporate\CorporateFollowUpRequestDTO;
use App\DTO\Corporate\CorporateFollowUpResultDTO;
use App\DTO\Corporate\CorporateIdentityInitializeRequestDTO;
use App\DTO\Corporate\CorporateInitializeResponseDTO;
use App\Helper\ResponseHelper;
use App\Service\Corporate\CorporateFollowUpRequestMapper;
use App\Service\Corporate\CorporateFollowUpService;
use App\Service\Corporate\CorporateIdentityInitializeRequestMapper;
use App\Service\Corporate\CorporateIdentityInitializeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CorporateRegistrationControllerTest extends TestCase
{
    public function testServiceIdentityRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(CorporateRegistrationController::class, 'serviceIdentity');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testServiceRegistrationRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(CorporateRegistrationController::class, 'serviceRegistration');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testServiceIdentityReturnsGeneratedResponse(): void
    {
        $request = Request::create('/api/registration/corporate/identity/create/initialize', 'POST');
        $validatedPayload = [
            'getIdentity' => json_encode([
                'publicId' => 'public-1',
                'scope' => 'internal',
                'businessModel' => 'businessBasic',
            ], JSON_THROW_ON_ERROR),
        ];
        $initializeRequest = CorporateIdentityInitializeRequestDTO::fromArray([
            'publicId' => 'public-1',
            'scope' => 'internal',
            'businessModel' => 'businessBasic',
        ]);
        $result = new CorporateInitializeResponseDTO('encrypted-body', ['X-Test' => 'header']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'getIdentity')
            ->willReturn($validatedPayload);

        $initializeMapper = $this->createMock(CorporateIdentityInitializeRequestMapper::class);
        $initializeMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($initializeRequest);

        $initializeService = $this->createMock(CorporateIdentityInitializeService::class);
        $initializeService
            ->expects(self::once())
            ->method('handle')
            ->with($initializeRequest)
            ->willReturn($result);

        $followUpMapper = $this->createMock(CorporateFollowUpRequestMapper::class);
        $followUpService = $this->createMock(CorporateFollowUpService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::never())
            ->method('handleException');

        $controller = new CorporateRegistrationController($payloadValidator, $initializeMapper, $initializeService, $followUpMapper, $followUpService);
        $response = $controller->serviceIdentity($request, $responseHelper);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('encrypted-body', $response->getContent());
        self::assertSame('header', $response->headers->get('X-Test'));
    }

    public function testServiceRegistrationReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/registration/corporate/identity/create/follow-up', 'POST');
        $validatedPayload = [
            'updateIdentity' => ['corporateId' => 'corp-1'],
        ];
        $followUpRequest = new CorporateFollowUpRequestDTO($validatedPayload);
        $result = CorporateFollowUpResultDTO::success();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'updateIdentity')
            ->willReturn($validatedPayload);

        $initializeMapper = $this->createMock(CorporateIdentityInitializeRequestMapper::class);
        $initializeService = $this->createMock(CorporateIdentityInitializeService::class);

        $followUpMapper = $this->createMock(CorporateFollowUpRequestMapper::class);
        $followUpMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($followUpRequest);

        $followUpService = $this->createMock(CorporateFollowUpService::class);
        $followUpService
            ->expects(self::once())
            ->method('handle')
            ->with($followUpRequest)
            ->willReturn($result);

        $controller = new CorporateRegistrationController($payloadValidator, $initializeMapper, $initializeService, $followUpMapper, $followUpService);
        $response = $controller->serviceRegistration($request, $this->createMock(ResponseHelper::class));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('1', $response->getContent());
    }

    public function testServiceIdentityUsesResponseHelperOnValidationErrors(): void
    {
        $request = Request::create('/api/registration/corporate/identity/create/initialize', 'POST');
        $exception = new \InvalidArgumentException('Invalid corporate initialize payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'getIdentity')
            ->willThrowException($exception);

        $initializeMapper = $this->createMock(CorporateIdentityInitializeRequestMapper::class);
        $initializeMapper->expects(self::never())->method('map');
        $initializeService = $this->createMock(CorporateIdentityInitializeService::class);
        $initializeService->expects(self::never())->method('handle');
        $followUpMapper = $this->createMock(CorporateFollowUpRequestMapper::class);
        $followUpService = $this->createMock(CorporateFollowUpService::class);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new CorporateRegistrationController($payloadValidator, $initializeMapper, $initializeService, $followUpMapper, $followUpService);

        self::assertSame($errorResponse, $controller->serviceIdentity($request, $responseHelper));
    }
}
