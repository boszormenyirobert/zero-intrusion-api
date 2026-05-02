<?php

declare(strict_types=1);

namespace App\Tests\Controller\Account;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\Account\AccountController;
use App\DTO\Account\AccountRequestDTO;
use App\DTO\Account\AccountResponseDTO;
use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;
use App\Helper\ResponseHelper;
use App\Service\Account\AccountLookupService;
use App\Service\Account\AccountRequestMapper;
use App\Service\Account\AccountRequestResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class AccountControllerTest extends TestCase
{
    public function testAccountRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(AccountController::class, 'account');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testAccountReturnsResolvedData(): void
    {
        $request = Request::create('/api/account/all', 'POST');
        $validatedPayload = [
            'get_registrated_business' => [
                'publicId' => 'public-1',
                'email' => 'user@example.test',
            ],
        ];
        $accountRequest = new AccountRequestDTO('public-1', 'user@example.test');
        $accountResponse = new AccountResponseDTO([
            ['corporateId' => 'corp-1'],
        ], ['id' => 9, 'pro' => true]);

        $requestResolver = $this->createMock(AccountRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($request)
            ->willReturn($validatedPayload);

        $requestMapper = $this->createMock(AccountRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($accountRequest);

        $lookupService = $this->createMock(AccountLookupService::class);
        $lookupService
            ->expects(self::once())
            ->method('handle')
            ->with($accountRequest)
            ->willReturn($accountResponse);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::never())->method('handleException');

        $controller = new AccountController($requestResolver, $requestMapper, $lookupService, $responseHelper);
        $response = $controller->account($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($accountResponse->toArray(), json_decode((string) $response->getContent(), true));
    }

    public function testAccountUsesResponseHelperOnResolverError(): void
    {
        $request = Request::create('/api/account/all', 'POST');
        $exception = new \InvalidArgumentException('Invalid account payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $requestResolver = $this->createMock(AccountRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($request)
            ->willThrowException($exception);

        $requestMapper = $this->createMock(AccountRequestMapper::class);
        $requestMapper->expects(self::never())->method('map');

        $lookupService = $this->createMock(AccountLookupService::class);
        $lookupService->expects(self::never())->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new AccountController($requestResolver, $requestMapper, $lookupService, $responseHelper);

        self::assertSame($errorResponse, $controller->account($request));
    }

    public function testAccountReturnsSerializedEntityPayloadsForFrontendConsumers(): void
    {
        $request = Request::create('/api/account/all', 'POST');
        $validatedPayload = [
            'get_registrated_business' => [
                'publicId' => 'public-1',
                'email' => 'user@example.test',
            ],
        ];
        $accountRequest = new AccountRequestDTO('public-1', 'user@example.test');

        $account = (new CorporateIdentity())
            ->setDomain('example.com')
            ->setCallbackUserLogin('https://example.com/login')
            ->setCallbackUserRegistration('https://example.com/register')
            ->setCorporateIdKey('key-1')
            ->setCorporateIdSecret('secret-1')
            ->setIv(base64_encode(random_bytes(16)))
            ->setCorporateId('corp-1')
            ->setSslPrivateKey('private-key')
            ->setSslPublicKey('public-key');

        $businessService = (new BusinessServices())
            ->setPasswordManager(true)
            ->setBiometric(false)
            ->setBasic(false)
            ->setPlus(false)
            ->setPro(false);

        $idProperty = new \ReflectionProperty(BusinessServices::class, 'id');
        $idProperty->setValue($businessService, 9);

        $accountResponse = new AccountResponseDTO([$account], $businessService);

        $requestResolver = $this->createMock(AccountRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($request)
            ->willReturn($validatedPayload);

        $requestMapper = $this->createMock(AccountRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($accountRequest);

        $lookupService = $this->createMock(AccountLookupService::class);
        $lookupService
            ->expects(self::once())
            ->method('handle')
            ->with($accountRequest)
            ->willReturn($accountResponse);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::never())->method('handleException');

        $controller = new AccountController($requestResolver, $requestMapper, $lookupService, $responseHelper);
        $response = $controller->account($request);
        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame('example.com', $payload['accounts'][0]['domain'] ?? null);
        self::assertSame('public-key', $payload['accounts'][0]['sslPublicKey'] ?? null);
        self::assertTrue($payload['businessSubscription']['pswManager'] ?? false);
        self::assertSame(9, $payload['businessSubscription']['id'] ?? null);
    }
}
