<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Domain\Delete;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\Domain\Delete\DomainDeleteController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteCredentialResultDTO;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteCredentialService;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityService;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class DomainDeleteControllerTest extends TestCase
{
    public function testDomainDeleteControllerRemovesHistoricalComments(): void
    {
        $reflection = new \ReflectionClass(DomainDeleteController::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('Generates a QR code for domain deletion.', $source);
        self::assertStringNotContainsString('Handles credential-based domain deletion.', $source);
        self::assertStringNotContainsString('Checks whether the domain deletion process has completed.', $source);
    }

    public function testDomainDeleteRoutesRequireExpectedAttributes(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteQrIdentity'))->getAttributes(RequireHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteCredential'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteState'))->getAttributes(ExtensionHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(DomainDeleteController::class, 'domainDeleteState'))->getAttributes(RequireJson::class));
    }

    public function testDomainDeleteQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/qr-identity', 'POST');
        $validatedPayload = ['domain_delete_qr_identity' => '{"targetId":"target-1"}'];
        $dto = DomainDeleteQrIdentityRequestDTO::fromArray(['targetId' => 'target-1']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator->expects(self::once())->method('validatePayload')->with($request, 'domain_delete_qr_identity')->willReturn($validatedPayload);
        $mapper = $this->createMock(DomainDeleteQrIdentityRequestMapper::class);
        $mapper->expects(self::once())->method('map')->with($validatedPayload)->willReturn($dto);
        $qrIdentityService = $this->createMock(DomainDeleteQrIdentityService::class);
        $qrIdentityService->expects(self::once())->method('handle')->with($dto)->willReturn(['removeProcessId' => 'process-1']);
        $credentialService = $this->createMock(DomainDeleteCredentialService::class);
        $stateService = $this->createMock(DomainDeleteStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['removeProcessId' => 'process-1'])->willReturn(new JsonResponse(['removeProcessId' => 'process-1']));

        $controller = new DomainDeleteController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->domainDeleteQrIdentity($request);

        self::assertSame(['removeProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testDomainDeleteCredentialReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/credential', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(DomainDeleteQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(DomainDeleteQrIdentityService::class);
        $credentialService = $this->createMock(DomainDeleteCredentialService::class);
        $credentialService->expects(self::once())->method('handle')->with($request)->willReturn(new DomainDeleteCredentialResultDTO(true, ''));
        $stateService = $this->createMock(DomainDeleteStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new DomainDeleteController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->domainDeleteCredential($request);

        self::assertSame(['delete_process' => true, 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testDomainDeleteStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/state', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(DomainDeleteQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(DomainDeleteQrIdentityService::class);
        $credentialService = $this->createMock(DomainDeleteCredentialService::class);
        $stateService = $this->createMock(DomainDeleteStateService::class);
        $stateService->expects(self::once())->method('handle')->with($request)->willReturn(['process_check' => true]);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['process_check' => true])->willReturn(new JsonResponse(['process_check' => true]));

        $controller = new DomainDeleteController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->domainDeleteState($request);

        self::assertSame(['process_check' => true], json_decode((string) $response->getContent(), true));
    }
}