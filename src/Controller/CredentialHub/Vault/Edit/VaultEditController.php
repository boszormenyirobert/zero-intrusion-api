<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Vault\Edit;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Vault\Edit\VaultEditCredentialService;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityService;
use App\Service\CredentialHub\Vault\Edit\VaultEditStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/credential-hub/vault/edit')]
class VaultEditController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly VaultEditQrIdentityRequestMapper $vaultEditQrIdentityRequestMapper,
        private readonly VaultEditQrIdentityService $vaultEditQrIdentityService,
        private readonly VaultEditCredentialService $vaultEditCredentialService,
        private readonly VaultEditStateService $vaultEditStateService,
    ) {
    }

    #[Route('/qr-identity', name: 'vault_edit_qr_identity', methods: "POST")]
//    #[RequireHmac]
    #[RequireJson]
    public function vaultEditQrIdentity(
        Request $request,
    ): JsonResponse
    {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::VAULT_EDIT_QR_IDENTITY);
            $vaultEditRequest = $this->vaultEditQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->vaultEditQrIdentityService->handle($vaultEditRequest)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/credential', name: 'vault_edit_credential', methods: "POST")]
//    #[RequireHmac]
//    #[MobileHmac]    
    #[RequireJson]
    public function vaultEditCredential(
        Request $request,
    ): JsonResponse
    {
        try {
            $response = $this->vaultEditCredentialService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return new JsonResponse($response->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/state', name: 'vault_edit_state', methods: "POST")]
//    #[RequireHmac]
    #[RequireJson]
//    #[ExtensionHmac]    
    public function vaultEditState(
        Request $request,
    ): JsonResponse {
        try {
            $response = $this->vaultEditStateService->handle($request);

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