<?php

namespace App\Controller\CredentialHub\Domain\Read;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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
use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\Controller\CredentialHub\PayloadKeys;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Controller\CredentialHub\SharedService;

#[Route('/api/credential-hub/domain/read')]
class DomainReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private PayloadValidator $payloadValidator,
        private ResponseHelper $responseHelper,
        private SharedService $sharedService
    ) {}


    /**
     * Called by Browser-Extension
     * 
     * This is used to create a browser extension DOMAIN identity
     *
     * Generate two HMAC and applicationProcessId
     * Generated HMAC added to the extension Header as X-Extension-Auth  to verify the identity
     * Generated HMAC included in the QR-Code. Used by Mobile App - added to the header - to verify the identity
     * The generated processId is added to the QR-Code and to the extension body as applicationProcessId
     * 
     * Saved in the AuthBridge Database
     * 
     * The database is automatically cleaned by a cron-based process:
     * any row older than X minutes is automatically deleted.     
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/qr-identity', name: 'domain_read_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function domainReadQrIdentity(
        Request $request,
        AuthBridgeService $authBridgeService,
        QrService $qrService,
        DomainReadService $domainReadService,
        ValidatorInterface $validator
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_READ_QR_IDENTITY;
        $processKey = PayloadKeys::DOMAIN_PROCESS_ID;

        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $domain = $validatedPayload[$payloadKey]['domain'];
            $userPublicId = $validatedPayload[$payloadKey]['userPublicId'];
           
            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */
            $identity = $authBridgeService->generateRequestIdentity($processKey);

            // X-Extension-Auth-One used always from mobile application to verify the identity
            $authToken = $identity->getXExtensionAuthOne();
            
            // Generate QR Content from the identity, domain and authToken
            $qrContent = $domainReadService->getQrContent($domain, $authToken, $identity);

            // Validate the QR Content DTO
            $errors = $validator->validate($qrContent);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->logger->critical('domainReadQrIdentity: ' . $error->getMessage());
                }
            }

            // Generate a base64-encoded PNG QR code from input data.
            $qrCode = $qrService->getQrCode($qrContent);

            // Extend the identity with the generated QR code
            $identity->setQrCode($qrCode);

            // Notify the Mobile App with FCM. Extension get the same response with QR code
            $this->sharedService->sendFcmNotification(
                'domainRead',
                $userPublicId,
                $qrContent
            );
            
            return $this->responseHelper->createSuccessResponse(
                $identity->toDomainProcessArray()
            );
            
        } catch (\Exception $e) {
            $this->logger->critical(\json_encode($e->getMessage()));
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
    #[Route('/credential/decrypted', name: 'domain_read_credential_encrypted', methods: "POST")]
    public function domainReadCredentialDecrypted(
        Request $request,
        DomainReadService $domainReadService
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_READ_CREDENTIAL_ENCRYPTED;
 
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $user = $validatedPayload[$payloadKey];
            
            // User credentials by domain decryped by Database but enrcypted by the userSecret
            $response = $domainReadService->getDecryptedCredentials($user);
            return $this->responseHelper->createSuccessResponse(['credentials' => $response]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Mobile App
     * 
     * Find and decrypt to the domains related credentials in the AccessRegistry by PublicId
     * Encrypt and move into the AuthBridge table the related record by domainProcessId
     * Decrypt and Update the Record with the credentials
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/credential', name: 'domain_read_credential', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]
    #[RequireJson]
    public function domainReadCredential(
        Request $request,
        DomainReadService $domainReadService
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_READ_CREDENTIAL;

        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $user = $validatedPayload[$payloadKey];

            // Process the credential read request
            $response = $domainReadService->processCredentialRead($user);

            return $this->responseHelper->createSuccessResponse(['credentials' => $response]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Browser-Extension
     * Get User Credentials By domainProcessId from the AuthBridge
     * Delete the record from the AuthBridge
     * Get User Email and publicId by targetId for auto-notification
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/state', name: 'domain_read_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function domainReadState(
        Request $request,
        AuthBridgeService $authBridgeService
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_READ_STATE;

        try {
            $processId = $this->sharedService->getProcessId($request, $payloadKey);

            if (!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $authBridgeService->fetchFromAccessTable($processId, 'domain');
            
            /** @var array{email: ?string, publicId: ?string} $toAutoNotification */
            $toAutoNotification = $this->sharedService->getUserEmailByTargetId($response);
            return $this->responseHelper->createSuccessResponse(
                array_merge(
                    ['domainList' => $response['response']],
                    $response['process'],
                    $toAutoNotification
                ));
        } catch (\Exception $e) {
            $this->logger->critical('Error: ' . $e->getMessage());
            return $this->responseHelper->handleException($e, ['login_process_check' => false]);            
        }        
    }
}
