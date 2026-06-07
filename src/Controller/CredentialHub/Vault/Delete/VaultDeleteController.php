<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Vault\Delete;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteCredentialService;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteQrIdentityService;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/credential-hub/vault/delete')]
class VaultDeleteController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly VaultDeleteQrIdentityRequestMapper $vaultDeleteQrIdentityRequestMapper,
        private readonly VaultDeleteQrIdentityService $vaultDeleteQrIdentityService,
        private readonly VaultDeleteCredentialService $vaultDeleteCredentialService,
        private readonly VaultDeleteStateService $vaultDeleteStateService,
    ) {
    }

    #[Route('/qr-identity', name: 'vault_delete_qr_identity', methods: "POST")]
    #[RequireJson]
    public function vaultDeleteQrIdentity(
        Request $request
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::VAULT_DELETE_QR_IDENTITY);
            $vaultDeleteRequest = $this->vaultDeleteQrIdentityRequestMapper->map($validatedPayload);

            $response = $this->vaultDeleteQrIdentityService->handle($vaultDeleteRequest);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return $this->responseHelper->createSuccessResponse($response);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/credential', name: 'vault_delete_credential', methods: "POST")]
    #[RequireJson]
    public function vaultDeleteCredential(
        Request $request,
    ): JsonResponse
    {
        try {
            $response = $this->vaultDeleteCredentialService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return new JsonResponse($response->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/state', name: 'vault_delete_state', methods: "POST")]
    #[RequireJson]
    public function vaultDeleteState(
        Request $request,
    ): JsonResponse {
        try {
            $response = $this->vaultDeleteStateService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return $this->responseHelper->createSuccessResponse($response ?? []);

        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    private function missingProcessResponse(): JsonResponse
    {
        return $this->responseHelper->createErrorResponse('Invalid or missing processId');
    }
}
