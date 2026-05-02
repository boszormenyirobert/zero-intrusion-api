<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Vault\Read;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\Vault\Read\VaultReadController;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Vault\Read\VaultReadCredentialResultDTO;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialService;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityService;
use App\Service\CredentialHub\Vault\Read\VaultReadStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class VaultReadControllerTest extends TestCase
{
    public function testVaultReadControllerRemovesHistoricalComments(): void
    {
        $reflection = new \ReflectionClass(VaultReadController::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('This is used to read a browser extension VAULT identity', $source);
        self::assertStringNotContainsString('Database automatically cleared by cronjob.', $source);
        self::assertStringNotContainsString('@param Request', $source);
    }

    public function testVaultReadRoutesRequireExpectedAttributes(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(VaultReadController::class, 'vaultReadQrIdentity'))->getAttributes(RequireHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultReadController::class, 'vaultReadCredentialDecrypted'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultReadController::class, 'vaultReadCredential'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultReadController::class, 'vaultReadState'))->getAttributes(ExtensionHmac::class));
    }

    public function testVaultReadQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/qr-identity', 'POST');
        $validatedPayload = ['vault_read_qr_identity' => ['source' => 'extension', 'type' => 'applications']];
        $dto = VaultReadQrIdentityRequestDTO::fromArray($validatedPayload['vault_read_qr_identity']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator->expects(self::once())->method('validatePayload')->with($request, 'vault_read_qr_identity')->willReturn($validatedPayload);
        $mapper = $this->createMock(VaultReadQrIdentityRequestMapper::class);
        $mapper->expects(self::once())->method('map')->with($validatedPayload)->willReturn($dto);
        $qrIdentityService = $this->createMock(VaultReadQrIdentityService::class);
        $qrIdentityService->expects(self::once())->method('handle')->with($dto)->willReturn(['applicationProcessId' => 'process-1']);
        $credentialDecryptedService = $this->createMock(VaultReadCredentialDecryptedService::class);
        $credentialService = $this->createMock(VaultReadCredentialService::class);
        $stateService = $this->createMock(VaultReadStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['applicationProcessId' => 'process-1'])->willReturn(new JsonResponse(['applicationProcessId' => 'process-1']));

        $controller = new VaultReadController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialDecryptedService, $credentialService, $stateService);
        $response = $controller->vaultReadQrIdentity($request);

        self::assertSame(['applicationProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testVaultReadCredentialReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/credential', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(VaultReadQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(VaultReadQrIdentityService::class);
        $credentialDecryptedService = $this->createMock(VaultReadCredentialDecryptedService::class);
        $credentialService = $this->createMock(VaultReadCredentialService::class);
        $credentialService->expects(self::once())->method('handle')->with($request)->willReturn(new VaultReadCredentialResultDTO(true, ''));
        $stateService = $this->createMock(VaultReadStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new VaultReadController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialDecryptedService, $credentialService, $stateService);
        $response = $controller->vaultReadCredential($request);

        self::assertSame(['application_access_process' => true, 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testVaultReadStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/state', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $mapper = $this->createMock(VaultReadQrIdentityRequestMapper::class);
        $qrIdentityService = $this->createMock(VaultReadQrIdentityService::class);
        $credentialDecryptedService = $this->createMock(VaultReadCredentialDecryptedService::class);
        $credentialService = $this->createMock(VaultReadCredentialService::class);
        $stateService = $this->createMock(VaultReadStateService::class);
        $stateService->expects(self::once())->method('handle')->with($request)->willReturn(['process_check' => true]);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['process_check' => true])->willReturn(new JsonResponse(['process_check' => true]));

        $controller = new VaultReadController($payloadValidator, $responseHelper, $mapper, $qrIdentityService, $credentialDecryptedService, $credentialService, $stateService);
        $response = $controller->vaultReadState($request);

        self::assertSame(['process_check' => true], json_decode((string) $response->getContent(), true));
    }
}