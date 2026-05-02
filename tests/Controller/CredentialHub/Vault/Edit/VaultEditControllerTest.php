<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Vault\Edit;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\Vault\Edit\VaultEditController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Vault\Edit\VaultEditCredentialResultDTO;
use App\DTO\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Edit\VaultEditCredentialService;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityService;
use App\Service\CredentialHub\Vault\Edit\VaultEditStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class VaultEditControllerTest extends TestCase
{
    public function testVaultEditControllerRemovesLegacyFlowComments(): void
    {
        $reflection = new \ReflectionClass(VaultEditController::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('TODO', $source);
        self::assertStringNotContainsString('Flow:', $source);
    }

    public function testVaultEditRoutesRequireExpectedAttributes(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(VaultEditController::class, 'vaultEditQrIdentity'))->getAttributes(RequireHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultEditController::class, 'vaultEditCredential'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultEditController::class, 'vaultEditState'))->getAttributes(ExtensionHmac::class));
    }

    public function testVaultEditQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/vault/edit/qr-identity', 'POST');
        $validatedPayload = ['vault_edit_qr_identity' => '{"type":"registration-application"}'];
        $dto = VaultEditQrIdentityRequestDTO::fromArray(['type' => 'registration-application']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator->expects(self::once())->method('validatePayload')->with($request, 'vault_edit_qr_identity')->willReturn($validatedPayload);
        $mapper = $this->createMock(VaultEditQrIdentityRequestMapper::class);
        $mapper->expects(self::once())->method('map')->with($validatedPayload)->willReturn($dto);
        $qrIdentityService = $this->createMock(VaultEditQrIdentityService::class);
        $qrIdentityService->expects(self::once())->method('handle')->with($dto)->willReturn(['registrationProcessId' => 'process-1']);
        $credentialService = $this->createMock(VaultEditCredentialService::class);
        $stateService = $this->createMock(VaultEditStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['registrationProcessId' => 'process-1'])->willReturn(new JsonResponse(['registrationProcessId' => 'process-1']));

        $controller = new VaultEditController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->vaultEditQrIdentity($request);

        self::assertSame(['registrationProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testVaultEditCredentialReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/vault/edit/credential', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(VaultEditQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(VaultEditQrIdentityService::class);
        $credentialService = $this->createMock(VaultEditCredentialService::class);
        $credentialService->expects(self::once())->method('handle')->with($request)->willReturn(new VaultEditCredentialResultDTO(true, ''));
        $stateService = $this->createMock(VaultEditStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new VaultEditController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->vaultEditCredential($request);

        self::assertSame(['delete_process' => true, 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testVaultEditStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/vault/edit/state', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(VaultEditQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(VaultEditQrIdentityService::class);
        $credentialService = $this->createMock(VaultEditCredentialService::class);
        $stateService = $this->createMock(VaultEditStateService::class);
        $stateService->expects(self::once())->method('handle')->with($request)->willReturn(['process_check' => true]);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['process_check' => true])->willReturn(new JsonResponse(['process_check' => true]));

        $controller = new VaultEditController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->vaultEditState($request);

        self::assertSame(['process_check' => true], json_decode((string) $response->getContent(), true));
    }
}