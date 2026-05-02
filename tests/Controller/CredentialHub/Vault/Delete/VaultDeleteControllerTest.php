<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Vault\Delete;

use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireHmac;
use App\Controller\CredentialHub\Vault\Delete\VaultDeleteController;
use App\DTO\CredentialHub\Vault\Delete\VaultDeleteCredentialResultDTO;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteCredentialService;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteQrIdentityService;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class VaultDeleteControllerTest extends TestCase
{
    public function testVaultDeleteControllerRemovesLegacyComments(): void
    {
        $reflection = new \ReflectionClass(VaultDeleteController::class);
        $source = file_get_contents($reflection->getFileName());

        self::assertIsString($source);
        self::assertStringNotContainsString('Called by Browser-Extension', $source);
        self::assertStringNotContainsString('Called by Mobile App', $source);
    }

    public function testVaultDeleteRoutesRequireExpectedAttributes(): void
    {
        self::assertNotEmpty((new \ReflectionMethod(VaultDeleteController::class, 'vaultDeleteQrIdentity'))->getAttributes(RequireHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultDeleteController::class, 'vaultDeleteCredential'))->getAttributes(MobileHmac::class));
        self::assertNotEmpty((new \ReflectionMethod(VaultDeleteController::class, 'vaultDeleteState'))->getAttributes(ExtensionHmac::class));
    }

    public function testVaultDeleteQrIdentityReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/qr-identity', 'POST');

        $qrIdentityService = $this->createMock(VaultDeleteQrIdentityService::class);
        $qrIdentityService->expects(self::once())->method('handle')->with($request)->willReturn(['removeProcessId' => 'process-1']);
        $credentialService = $this->createMock(VaultDeleteCredentialService::class);
        $stateService = $this->createMock(VaultDeleteStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['removeProcessId' => 'process-1'])->willReturn(new JsonResponse(['removeProcessId' => 'process-1']));

        $controller = new VaultDeleteController($responseHelper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->vaultDeleteQrIdentity($request);

        self::assertSame(['removeProcessId' => 'process-1'], json_decode((string) $response->getContent(), true));
    }

    public function testVaultDeleteCredentialReturnsJsonPayload(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/credential', 'POST');

        $qrIdentityService = $this->createMock(VaultDeleteQrIdentityService::class);
        $credentialService = $this->createMock(VaultDeleteCredentialService::class);
        $credentialService->expects(self::once())->method('handle')->with($request)->willReturn(new VaultDeleteCredentialResultDTO(true, ''));
        $stateService = $this->createMock(VaultDeleteStateService::class);
        $responseHelper = $this->createMock(ResponseHelper::class);

        $controller = new VaultDeleteController($responseHelper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->vaultDeleteCredential($request);

        self::assertSame(['delete_process' => true, 'error' => ''], json_decode((string) $response->getContent(), true));
    }

    public function testVaultDeleteStateReturnsSuccessResponse(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/state', 'POST');

        $qrIdentityService = $this->createMock(VaultDeleteQrIdentityService::class);
        $credentialService = $this->createMock(VaultDeleteCredentialService::class);
        $stateService = $this->createMock(VaultDeleteStateService::class);
        $stateService->expects(self::once())->method('handle')->with($request)->willReturn(['process_check' => true]);
        $responseHelper = $this->createMock(ResponseHelper::class);
        $responseHelper->expects(self::once())->method('createSuccessResponse')->with(['process_check' => true])->willReturn(new JsonResponse(['process_check' => true]));

        $controller = new VaultDeleteController($responseHelper, $qrIdentityService, $credentialService, $stateService);
        $response = $controller->vaultDeleteState($request);

        self::assertSame(['process_check' => true], json_decode((string) $response->getContent(), true));
    }
}