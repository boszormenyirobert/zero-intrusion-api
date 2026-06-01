<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Vault\Read;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialService;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityRequestMapper;
use App\Service\CredentialHub\ReadQrIdentityService;
use App\Service\CredentialHub\IdentityType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\CredentialHub\SharedSSE;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Route('/api/credential-hub/vault/read')]
class VaultReadController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly VaultReadQrIdentityRequestMapper $vaultReadQrIdentityRequestMapper,
        private readonly ReadQrIdentityService $readQrIdentityService,
        private readonly VaultReadCredentialDecryptedService $vaultReadCredentialDecryptedService,
        private readonly VaultReadCredentialService $vaultReadCredentialService,
        private readonly SharedSSE $sharedSSE
    ) {
    }
    #[Route('/qr-identity', name: 'vault_read_qr_identity', methods: "POST")]
    #[RequireJson]
    public function vaultReadQrIdentity(
        Request $request,
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::VAULT_READ_QR_IDENTITY);
            $readRequest = $this->vaultReadQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->readQrIdentityService->handle($readRequest, IdentityType::VAULT_READ)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[RequireJson]
    #[Route('/credential/decrypted', name: 'vault_read_credential_encrypted', methods: "POST")]
    public function vaultReadCredentialDecrypted(
        Request $request,
    ): JsonResponse {
        try {
            return $this->responseHelper->createSuccessResponse(
                $this->vaultReadCredentialDecryptedService->handle($request)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }
    #[Route('/credential', name: 'vault_read_credential', methods: "POST")]
    #[RequireJson]
    public function vaultReadCredential(
        Request $request,
    ): JsonResponse {
        try {
            return $this->responseHelper->createSuccessResponse(
                $this->vaultReadCredentialService->handle($request)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/approval-challange/{key}', name: 'api_sse_vault', methods: ['GET'])]
    public function sse(string $key): StreamedResponse
    {
        return $this->sharedSSE->handle($key);
    }
}
