<?php

namespace App\Controller\CredentialHub\Vault\Read;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\AuthBridge\AuthBridgeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Helper\ResponseHelper;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Service\QrService\QrService;
use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\SharedService;
use App\Service\Firebase\FirebaseService;

#[Route('/api/credential-hub/vault/read')]
class VaultReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private PayloadValidator $payloadValidator,
        private ResponseHelper $responseHelper,
        private AuthBridgeService $authBridgeService,
        private SharedService $sharedService
    ) {}

    /**
     * Called by Browser-Extension
     * 
     * This is used to read a browser extension VAULT identity
     *
     * Generate two HMAC and applicationProcessId
     * Generated HMAC added to the extension Header as X-Extension-Auth  to verify the identity
     * Generated HMAC included in the QR-Code. Used by Mobile App - added to the header - to verify the identity
     * The generated processId is added to the QR-Code and to the extension body as applicationProcessId
     * 
     * Saved in the AuthBridge Database
     * 
     * Database automatically cleared by cronjob. If row id older than X Sec will be deleted.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/qr-identity', name: 'vault_read_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function vaultReadQrIdentity(
        Request $request,
        QrService $qrService,
        VaultReadService $vaultReadService
    ): JsonResponse {
        $payloadKey = PayloadKeys::VAULT_READ_QR_IDENTITY;
        $processKey = PayloadKeys::VAULT_PROCESS_ID;

        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $source = $validatedPayload[$payloadKey]['source'];
            $type = $validatedPayload[$payloadKey]['type'];

            if ($source === 'extension' && $type === 'applications') {
                /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */                
                $identity = $this->authBridgeService->generateRequestIdentity($processKey);
            }

            $qrContent = $vaultReadService->getQrContent($type, $source, $identity->getXExtensionAuthOne(), $identity);
            $qrCode = $qrService->getQrCode($qrContent);
            $identity->setQrCode($qrCode);
            $this->logger->critical('Vault Read QR Content: ', (array)$validatedPayload[$payloadKey]);
            if(isset($validatedPayload[$payloadKey]['userPublicId']) && $validatedPayload[$payloadKey]['userPublicId'])
            {                 
                $this->sharedService->sendFcmNotification(
                    'vaultRead',
                    $validatedPayload[$payloadKey]['userPublicId'],
                    $qrContent
                );
            }     

            return $this->responseHelper->createSuccessResponse($identity->toApplicationProcessArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Mobile App
     * 
     * DB decrypt user credentials to the domains in the AccessRegistry by PublicId 
     * Return with the user-secrets encrypted credentials to the Mobile App for decryption
     * @param Request $request
     * @return JsonResponse
     */
    #[RequireHmac]
    #[MobileHmac]
    #[RequireJson]
    #[Route('/credential/decrypted', name: 'vault_read_credential_encrypted', methods: "POST")]
    public function vaultReadCredentialDecrypted(
        Request $request,
        VaultReadService $vaultReadService
    ): JsonResponse {
        $payloadKey = PayloadKeys::VAULT_READ_CREDENTIAL_ENCRYPTED;
 
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $user = $validatedPayload[$payloadKey];
            
            // User credentials by domain decryped by Database but enrcypted by the userSecret
            $this->logger->critical('Vault Read Decrypted Credentials: ' . json_encode($user['publicId']));
            $response = $vaultReadService->getDecryptedCredentials($user['publicId']);
            return $this->responseHelper->createSuccessResponse(['credentials' => $response]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Mobile App
     * 
     * Find applications in the AccessRegistry by PublicId
     * Move into the AuthBridge table the related record by applicationProcessId
     * Updated the Record with the credentials
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/credential', name: 'vault_read_credential', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function vaultReadCredential(
        Request $request,
        SharedService $sharedService
    ): Response {
        $payloadKey = PayloadKeys::VAULT_READ_CREDENTIAL;

        try {
            $process = $sharedService->getProcessId($request, $payloadKey, true); 
            if(!$process) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }
            
            $applicationListAdded = $this->authBridgeService->persistDecryptedUserData($process);

            return $this->json([
                'application_access_process' => $applicationListAdded,
                'error' => ''
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Browser-Extension
     * Get User Credentials By applicationProcessId from the AuthBridge
     * Delete the record from the AuthBridge
     * Get User Email and publicId by targetId for auto-notification
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/state', name: 'vault_read_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function vaultReadState(
        Request $request,
        SharedService $sharedService
    ): JsonResponse {
        $payloadKey = PayloadKeys::VAULT_READ_STATE;

        try {
            $processId = $sharedService->getProcessId($request, $payloadKey);

            if(!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $this->authBridgeService->fetchFromAccessTable($processId, 'application');
            /** @var array{email: ?string, publicId: ?string} $toAutoNotification */
            $toAutoNotification = $this->sharedService->getUserEmailByTargetId($response);
            return $this->responseHelper->createSuccessResponse(
                array_merge(
                    ['applicationList' => $response['response']],
                    $response['process'],
                    $toAutoNotification
                )
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }
}
