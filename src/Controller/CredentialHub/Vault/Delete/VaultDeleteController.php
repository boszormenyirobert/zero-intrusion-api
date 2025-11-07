<?php

namespace App\Controller\CredentialHub\Vault\Delete;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Helper\ResponseHelper;
use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\Vault\Delete\VaultDeleteService;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\SharedService;

#[Route('/api/credential-hub/vault/delete')]
class VaultDeleteController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private ResponseHelper $responseHelper,
        private SharedService $sharedService
    ) {}

    /*
     * Called by Browser-Extension
    */
    #[Route('/qr-identity', name: 'vault_delete_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function vaultDeleteQrIdentity(
        Request $request
    ): JsonResponse {
        $payloadKey = PayloadKeys::VAULT_DELETE_QR_IDENTITY;
        $processKey = PayloadKeys::VAULT_DELETE_PROCESS_ID;

        try {
            $process = $this->sharedService->getProcessId($request, $payloadKey, true);

            if(!$process) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }
            $identity = $this->sharedService->generateRequestIdentity($process, $processKey);

            if(isset($process['userPublicId']) && $process['userPublicId'] &&
            isset($identity['qrCode']) && $identity['qrCode'])
            {     
                $this->sharedService->sendFcmNotification(
                    'vaultDelete',
                    $process['userPublicId'],
                    $identity['qrCode']
                );  
            }   

            return $this->responseHelper->createSuccessResponse((array)$identity);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /*
     * Called by Mobile App
    */
    #[Route('/credential', name: 'vault_delete_credential', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]
    #[RequireJson]
    public function vaultDeleteCredential(
        Request $request,
        VaultDeleteService $vaultDeleteService
        ): JsonResponse
    {
        $payloadKey = PayloadKeys::VAULT_DELETE_CREDENTIAL;

        try {
            $process = $this->sharedService->getProcessId($request, $payloadKey, true);

            if(!$process) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $vaultDeleteService->deleteApplication($process);

            return $this->json([
                'delete_process' => $response['processState'],
                'error' => $response['deletedFromRegistry'] ? null : 'Application not found or already deleted'
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Browser-Extension
     */
    #[Route('/state', name: 'vault_delete_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function vaultDeleteState(
        Request $request,
        AccessRegistryRegistrationService $accessRegistryRegistrationService
    ): JsonResponse {

        $payloadKey = PayloadKeys::VAULT_DELETE_STATE;
        $processKey = PayloadKeys::VAULT_DELETE_PROCESS_ID;

        try {
            $processId = $this->sharedService->getProcessId($request, $payloadKey);

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
