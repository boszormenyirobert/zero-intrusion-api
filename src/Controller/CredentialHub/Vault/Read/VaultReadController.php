<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Vault\Read;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialService;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityService;
use App\Service\CredentialHub\Vault\Read\VaultReadStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/credential-hub/vault/read')]
class VaultReadController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly VaultReadQrIdentityRequestMapper $vaultReadQrIdentityRequestMapper,
        private readonly VaultReadQrIdentityService $vaultReadQrIdentityService,
        private readonly VaultReadCredentialDecryptedService $vaultReadCredentialDecryptedService,
        private readonly VaultReadCredentialService $vaultReadCredentialService,
        private readonly VaultReadStateService $vaultReadStateService,
    ) {
    }
    #[Route('/qr-identity', name: 'vault_read_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function vaultReadQrIdentity(
        Request $request,
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::VAULT_READ_QR_IDENTITY);
            $vaultReadRequest = $this->vaultReadQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->vaultReadQrIdentityService->handle($vaultReadRequest)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }
    #[RequireHmac]
    #[MobileHmac]
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
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function vaultReadCredential(
        Request $request,
    ): JsonResponse {
        try {
            $response = $this->vaultReadCredentialService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return new JsonResponse($response->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }
    #[Route('/state', name: 'vault_read_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function vaultReadState(
        Request $request,
    ): JsonResponse {
        try {
            $payload = $this->vaultReadStateService->handle($request);

            if ($payload === null) {
                return $this->missingProcessResponse();
            }

            return $this->responseHelper->createSuccessResponse(
                $payload
            );

        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    private function missingProcessResponse(): JsonResponse
    {
        return $this->responseHelper->createErrorResponse('Invalid or missing processId');
    }
}
