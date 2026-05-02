<?php

declare(strict_types=1);

namespace App\Tests\Controller\Business;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\Business\BusinessController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Business\BusinessCreateRequestDTO;
use App\DTO\Business\BusinessCreateResponseDTO;
use App\Helper\ResponseHelper;
use App\Service\Business\BusinessCreateRequestMapper;
use App\Service\Business\BusinessCreateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class BusinessControllerTest extends TestCase
{
    public function testBusinessCreateRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(BusinessController::class, 'businessCreate');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testBusinessCreateReturnsGeneratedResponse(): void
    {
        $request = Request::create('/api/registration/corporate/business/create', 'POST');
        $validatedPayload = [
            'business_create' => json_encode([
                'businessModel' => 'businessBasic',
                'publicId' => 'public-1',
                'scope' => 'external',
            ], JSON_THROW_ON_ERROR),
        ];
        $businessRequest = BusinessCreateRequestDTO::fromArray([
            'businessModel' => 'businessBasic',
            'publicId' => 'public-1',
            'scope' => 'external',
        ]);
        $result = new BusinessCreateResponseDTO('encrypted-body', ['X-Test' => 'header']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'business_create')
            ->willReturn($validatedPayload);

        $requestMapper = $this->createMock(BusinessCreateRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($businessRequest);

        $businessCreateService = $this->createMock(BusinessCreateService::class);
        $businessCreateService
            ->expects(self::once())
            ->method('handle')
            ->with($businessRequest)
            ->willReturn($result);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::never())
            ->method('handleException');

        $controller = new BusinessController($payloadValidator, $requestMapper, $businessCreateService);
        $response = $controller->businessCreate($request, $responseHelper);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('encrypted-body', $response->getContent());
        self::assertSame('header', $response->headers->get('X-Test'));
    }

    public function testBusinessCreateUsesResponseHelperOnValidationErrors(): void
    {
        $request = Request::create('/api/registration/corporate/business/create', 'POST');
        $exception = new \InvalidArgumentException('Invalid business create payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->with($request, 'business_create')
            ->willThrowException($exception);

        $requestMapper = $this->createMock(BusinessCreateRequestMapper::class);
        $requestMapper
            ->expects(self::never())
            ->method('map');

        $businessCreateService = $this->createMock(BusinessCreateService::class);
        $businessCreateService
            ->expects(self::never())
            ->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new BusinessController($payloadValidator, $requestMapper, $businessCreateService);

        self::assertSame($errorResponse, $controller->businessCreate($request, $responseHelper));
    }
}
