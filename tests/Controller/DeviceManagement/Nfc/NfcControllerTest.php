<?php

declare(strict_types=1);

namespace App\Tests\Controller\DeviceManagement\Nfc;

use App\Attribute\DesktopHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\DeviceManagement\Nfc\NfcController;
use App\DTO\Device\Nfc\NfcDecryptRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\Device\Nfc\NfcDecryptRequestMapper;
use App\Service\Device\Nfc\NfcDecryptService;
use App\Service\Device\Nfc\NfcRequestResolver;
use App\Service\Device\Nfc\NfcUsersService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class NfcControllerTest extends TestCase
{
    public function testGetNfcUsersRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(NfcController::class, 'getNfcUsers');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(DesktopHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testDecryptCardDataRouteRequiresExpectedAttributes(): void
    {
        $reflectionMethod = new \ReflectionMethod(NfcController::class, 'decryptNfcCardData');

        self::assertNotEmpty($reflectionMethod->getAttributes(RequireHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(DesktopHmac::class));
        self::assertNotEmpty($reflectionMethod->getAttributes(RequireJson::class));
    }

    public function testGetNfcUsersReturnsServicePayload(): void
    {
        $request = Request::create('/api/nfc/users', 'POST');

        $usersService = $this->createMock(NfcUsersService::class);
        $usersService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['users' => [['puID' => 'public-1']]]);

        $requestResolver = $this->createMock(NfcRequestResolver::class);
        $decryptMapper = $this->createMock(NfcDecryptRequestMapper::class);
        $decryptService = $this->createMock(NfcDecryptService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::never())->method('handleException');

        $controller = new NfcController($usersService, $requestResolver, $decryptMapper, $decryptService, $responseHelper);
        $response = $controller->getNfcUsers($request);

        self::assertSame(['users' => [['puID' => 'public-1']]], json_decode((string) $response->getContent(), true));
    }

    public function testDecryptCardDataReturnsResolvedPayload(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST');
        $validatedPayload = ['api_nfc_decrypt' => ['userPublicId' => 'public-1', 'nfcData' => 'encrypted-payload']];
        $dto = new NfcDecryptRequestDTO('public-1', 'corp-1', 'encrypted-payload');

        $usersService = $this->createMock(NfcUsersService::class);
        $requestResolver = $this->createMock(NfcRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($request)
            ->willReturn($validatedPayload);

        $decryptMapper = $this->createMock(NfcDecryptRequestMapper::class);
        $decryptMapper
            ->expects(self::once())
            ->method('map')
            ->with($validatedPayload)
            ->willReturn($dto);

        $decryptService = $this->createMock(NfcDecryptService::class);
        $decryptService
            ->expects(self::once())
            ->method('handle')
            ->with($dto)
            ->willReturn(['puID' => 'public-1']);

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::never())->method('handleException');

        $controller = new NfcController($usersService, $requestResolver, $decryptMapper, $decryptService, $responseHelper);
        $response = $controller->decryptNfcCardData($request);

        self::assertSame(['puID' => 'public-1'], json_decode((string) $response->getContent(), true));
    }

    public function testDecryptCardDataUsesResponseHelperOnErrors(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST');
        $exception = new \InvalidArgumentException('Invalid NFC payload.');
        $errorResponse = new JsonResponse(['success' => false, 'error' => 'Invalid payload or missing required data.'], 400);

        $usersService = $this->createMock(NfcUsersService::class);
        $requestResolver = $this->createMock(NfcRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->with($request)
            ->willThrowException($exception);

        $decryptMapper = $this->createMock(NfcDecryptRequestMapper::class);
        $decryptMapper->expects(self::never())->method('map');
        $decryptService = $this->createMock(NfcDecryptService::class);
        $decryptService->expects(self::never())->method('handle');

        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper
            ->expects(self::once())
            ->method('handleException')
            ->with($exception)
            ->willReturn($errorResponse);

        $controller = new NfcController($usersService, $requestResolver, $decryptMapper, $decryptService, $responseHelper);

        $response = $controller->decryptNfcCardData($request);

        self::assertSame($errorResponse, $response);
    }
}
