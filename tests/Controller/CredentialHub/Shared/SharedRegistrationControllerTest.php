<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Shared;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\Shared\SharedRegistrationController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewResultDTO;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewToEncryptResultDTO;
use App\DTO\CredentialHub\Shared\SharedRegistrationQrIdentityRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Shared\SharedRegistrationNewService;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityRequestMapper;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityService;
use App\Service\CredentialHub\Shared\SharedRegistrationStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SharedRegistrationControllerTest extends TestCase
{
    public function testSharedRegistrationControllerRemovesLegacyTodoComments(): void
    {
        $reflection = new \ReflectionClass(SharedRegistrationController::class);
        $fileName = $reflection->getFileName();

        self::assertIsString($fileName);

        $source = file_get_contents($fileName);

        self::assertIsString($source);
        self::assertStringNotContainsString('TODO', $source);
        self::assertStringNotContainsString('Flow:', $source);
    }

    public function testSharedRegistrationRoutesRequireExpectedAttributes(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(SharedRegistrationController::class, 'sharedRegistrationQrIdentity'))->getAttributes(RequireHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(SharedRegistrationController::class, 'sharedRegistrationQrIdentity'))->getAttributes(RequireJson::class));
        self::assertNotEmpty((new \ReflectionMethod(SharedRegistrationController::class, 'sharedRegistrationNewToEncrypt'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(SharedRegistrationController::class, 'sharedRegistrationNew'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(SharedRegistrationController::class, 'sharedRegistrationState'))->getAttributes(ExtensionHmac::class));
    }

    public function testSharedRegistrationQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/qr-identity', 'POST');
        $validatedPayload = ['shared_registration_qr_identity' => '{"type":"registration-domain"}'];
        $dto = SharedRegistrationQrIdentityRequestDTO::fromArray(['type' => 'registration-domain']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator->expects(self::once())->method('validatePayload')->with($request, 'shared_registration_qr_identity')->willReturn($validatedPayload);

        $mapper = $this->createMock(SharedRegistrationQrIdentityRequestMapper::class);
        $mapper->expects(self::once())->method('map')->with($validatedPayload)->willReturn($dto);

        $qrIdentityService = $this->createMock(SharedRegistrationQrIdentityService::class);
        $qrIdentityService->expects(self::once())->method('handle')->with($dto, self::isInstanceOf(ValidatorInterface::class))->willReturn(['registrationProcessId' => 'process-1']);

        $newToEncryptService = $this->createMock(SharedRegistrationNewToEncryptService::class);
        $newService = $this->createMock(SharedRegistrationNewService::class);
        $stateService = $this->createMock(SharedRegistrationStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['registrationProcessId' => 'process-1'])->willReturn(new JsonResponse(['registrationProcessId' => 'process-1']));

        $controller = new SharedRegistrationController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $newToEncryptService, $newService, $stateService);
        $response = $controller->sharedRegistrationQrIdentity($request, $this->createMock(ValidatorInterface::class));

        self::assertSame(['registrationProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testSharedRegistrationNewToEncryptReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/new/to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(SharedRegistrationQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(SharedRegistrationQrIdentityService::class);
        $newToEncryptService = $this->createMock(SharedRegistrationNewToEncryptService::class);
        $newToEncryptService->expects(self::once())->method('handle')->with($request)->willReturn(new SharedRegistrationNewToEncryptResultDTO(['id' => 1], ''));
        $newService = $this->createMock(SharedRegistrationNewService::class);
        $stateService = $this->createMock(SharedRegistrationStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new SharedRegistrationController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $newToEncryptService, $newService, $stateService);
        $response = $controller->sharedRegistrationNewToEncrypt($request);

        self::assertSame(['registration_process_init' => ['id' => 1], 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testSharedRegistrationNewReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/new', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(SharedRegistrationQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(SharedRegistrationQrIdentityService::class);
        $newToEncryptService = $this->createMock(SharedRegistrationNewToEncryptService::class);
        $newService = $this->createMock(SharedRegistrationNewService::class);
        $newService
            ->expects(self::once())
            ->method('handle')
            ->with($request)
            ->willReturn(new SharedRegistrationNewResultDTO(['id' => 1], ''));
        $stateService = $this->createMock(SharedRegistrationStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new SharedRegistrationController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $newToEncryptService, $newService, $stateService);
        $response = $controller->sharedRegistrationNew($request);

        self::assertSame(['registration_process_one' => ['id' => 1], 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testSharedRegistrationStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/state', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(SharedRegistrationQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(SharedRegistrationQrIdentityService::class);
        $newToEncryptService = $this->createMock(SharedRegistrationNewToEncryptService::class);
        $newService = $this->createMock(SharedRegistrationNewService::class);
        $stateService = $this->createMock(SharedRegistrationStateService::class);
        $stateService->expects(self::once())->method('handle')->with($request)->willReturn(['process_check' => true]);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['process_check' => true])->willReturn(new JsonResponse(['process_check' => true]));

        $controller = new SharedRegistrationController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $newToEncryptService, $newService, $stateService);
        $response = $controller->sharedRegistrationState($request);

        self::assertSame(['process_check' => true], json_decode((string) $response->getContent(), true));
    }
}