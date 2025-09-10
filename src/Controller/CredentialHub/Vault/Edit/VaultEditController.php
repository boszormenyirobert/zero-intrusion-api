<?php

namespace App\Controller\CredentialHub\Vault\Edit;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\AccessRegistry\AccessRegistryVaultService;
use App\Service\AuthBridge\AuthBridgeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Helper\ResponseHelper;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Service\QrService\QrService;
use App\Controller\CredentialHub\Vault\Edit\VaultEditService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\SharedService;

#[Route('/api/credential-hub/vault/edit')]
class VaultEditController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private PayloadValidator $payloadValidator,
        private ResponseHelper $responseHelper,
        private AccessRegistryRegistrationService $accessRegistryRegistrationService
    ) {}

    /*
     * Called by Browser-Extension
    */    

    #[Route('/qr-identity', name: 'vault_edit_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function vaultEditQrIdentity(
        Request $request,
        QrService $qrService,
        VaultEditService $vaultEditService,
        AuthBridgeService $authBridgeService
        ): JsonResponse
    {
        // update and registration is the same process. We do not make any update. Delete and create new
        $payloadKey = PayloadKeys::VAULT_EDIT_QR_IDENTITY;
        $processKey = PayloadKeys::VAULT_EDIT_PROCESS_ID;        

        try {
            $validatedPayloadJson = $this->payloadValidator->validatePayload($request, $payloadKey);
            $validatedPayload = json_decode($validatedPayloadJson[$payloadKey]);
            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */
            $identity = $authBridgeService->generateRequestIdentity($processKey);

            $qrContent = $vaultEditService->getQrContent($validatedPayload, $identity->getXExtensionAuthOne(), $identity->getRegistrationProcessId());
            
            $qrCode = $qrService->getQrCode($qrContent);  
            $identity->setQrCode($qrCode);

            return $this->responseHelper->createSuccessResponse($identity->toRegistrationProcessArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /*
     * Called by Mobile App
    */
    #[Route('/credential', name: 'vault_edit_credential', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function vaultEditCredential(
        Request $request,
        SharedService $sharedService,
        AccessRegistryVaultService $accessRegistryVaultService
        ): JsonResponse
    {
        $payloadKey = PayloadKeys::VAULT_EDIT_CREDENTIAL;

        try {
            $process = $sharedService->getProcessId($request,  $payloadKey, true);

            if(!$process) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $accessRegistryVaultService->editApplicationAccessRegistry($process);

            return $this->json([
                'delete_process' => $response,
                'error' => ''
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Browser-Extension
     */
    #[Route('/state', name: 'vault_edit_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]    
    public function vaultDeleteState(
        Request $request,
        AccessRegistryRegistrationService $accessRegistryRegistrationService,
        SharedService $sharedService
    ): JsonResponse {
        $payloadKey = PayloadKeys::VAULT_EDIT_STATE;
        $processKey = PayloadKeys::VAULT_EDIT_PROCESS_ID;

        try {
            $processId = $sharedService->getProcessId($request, $payloadKey);

            if(!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $accessRegistryRegistrationService->getState($processId, $processKey);
            
            return $this->responseHelper->createSuccessResponse($response->toStateArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    } 
}