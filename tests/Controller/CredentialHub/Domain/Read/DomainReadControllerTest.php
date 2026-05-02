<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Domain\Read;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\Domain\Read\DomainReadController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Domain\Read\DomainReadQrIdentityRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialDecryptedService;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialService;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityService;
use App\Service\CredentialHub\Domain\Read\DomainReadStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DomainReadControllerTest extends TestCase
{
    public function testDomainReadControllerRemovesHistoricalComments(): void
    {
        $reflection = new \ReflectionClass(DomainReadController::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('This is used to create a browser extension DOMAIN identity', $source);
        self::assertStringNotContainsString('Generate two HMAC and applicationProcessId', $source);
        self::assertStringNotContainsString('@param Request', $source);
    }

    public function testDomainReadRoutesRequireExpectedAttributes(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(DomainReadController::class, 'domainReadQrIdentity'))->getAttributes(RequireHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainReadController::class, 'domainReadCredentialDecrypted'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainReadController::class, 'domainReadCredential'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainReadController::class, 'domainReadState'))->getAttributes(ExtensionHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainReadController::class, 'domainReadState'))->getAttributes(RequireJson::class));
    }

    public function testDomainReadQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/qr-identity', 'POST');
        $validatedPayload = ['domain_read_qr_identity' => ['domain' => 'example.com', 'userPublicId' => 'user-1']];
        $dto = DomainReadQrIdentityRequestDTO::fromArray($validatedPayload['domain_read_qr_identity']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator->expects(self::once())->method('validatePayload')->with($request, 'domain_read_qr_identity')->willReturn($validatedPayload);
        $mapper = $this->createMock(DomainReadQrIdentityRequestMapper::class);
        $mapper->expects(self::once())->method('map')->with($validatedPayload)->willReturn($dto);
        $qrIdentityService = $this->createMock(DomainReadQrIdentityService::class);
        $qrIdentityService->expects(self::once())->method('handle')->with($dto, self::isInstanceOf(ValidatorInterface::class))->willReturn(['domainProcessId' => 'process-1']);
        $credentialDecryptedService = $this->createMock(DomainReadCredentialDecryptedService::class);
        $credentialService = $this->createMock(DomainReadCredentialService::class);
        $stateService = $this->createMock(DomainReadStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['domainProcessId' => 'process-1'])->willReturn(new JsonResponse(['domainProcessId' => 'process-1']));

        $controller = new DomainReadController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialDecryptedService, $credentialService, $stateService);
        $response = $controller->domainReadQrIdentity($request, $this->createMock(ValidatorInterface::class));

        self::assertSame(['domainProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testDomainReadCredentialReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/credential', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(DomainReadQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(DomainReadQrIdentityService::class);
        $credentialDecryptedService = $this->createMock(DomainReadCredentialDecryptedService::class);
        $credentialService = $this->createMock(DomainReadCredentialService::class);
        $credentialService->expects(self::once())->method('handle')->with($request)->willReturn(['credentials' => true]);
        $stateService = $this->createMock(DomainReadStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['credentials' => true])->willReturn(new JsonResponse(['credentials' => true]));

        $controller = new DomainReadController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialDecryptedService, $credentialService, $stateService);
        $response = $controller->domainReadCredential($request);

        self::assertSame(['credentials' => true], json_decode((string) $response->getContent(), true));
    }

    public function testDomainReadStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/state', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(DomainReadQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(DomainReadQrIdentityService::class);
        $credentialDecryptedService = $this->createMock(DomainReadCredentialDecryptedService::class);
        $credentialService = $this->createMock(DomainReadCredentialService::class);
        $stateService = $this->createMock(DomainReadStateService::class);
        $stateService->expects(self::once())->method('handle')->with($request)->willReturn(['process_check' => true]);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['process_check' => true])->willReturn(new JsonResponse(['process_check' => true]));

        $controller = new DomainReadController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialDecryptedService, $credentialService, $stateService);
        $response = $controller->domainReadState($request);

        self::assertSame(['process_check' => true], json_decode((string) $response->getContent(), true));
    }
}