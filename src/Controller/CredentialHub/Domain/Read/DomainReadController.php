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
use App\Service\Firebase\FirebaseService;

#[Route('/api/credential-hub/domain/read')]
class DomainReadController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private PayloadValidator $payloadValidator,
        private ResponseHelper $responseHelper
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
     * Database automatically cleared by cronjob. If row id older than X Min will be deleted.
     * 
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
        ValidatorInterface $validator,
        FirebaseService $firebaseService
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_READ_QR_IDENTITY;
        $processKey = PayloadKeys::DOMAIN_PROCESS_ID;

        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $domain = $validatedPayload[$payloadKey]['domain'];
            $userPublicId = $validatedPayload[$payloadKey]['userPublicId'];
           
            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */
            $identity = $authBridgeService->generateRequestIdentity($processKey);
            $authToken = $identity->getXExtensionAuthOne();

            $qrContent = $domainReadService->getQrContent($domain, $authToken, $identity);
            $errors = $validator->validate($qrContent);

            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->logger->critical('domainReadQrIdentity: ' . $error->getMessage());
                }
            }

            $qrCode = $qrService->getQrCode($qrContent);
            $identity->setQrCode($qrCode);

            $this->logger->critical('qrCode : ' . $qrCode);

            if($userPublicId)
            {                
                $firebaseService->manageFcm(  
                    $userPublicId,                 
                    'From domain read',
                    'Forwarded the QR content, ordered by the user publicId',
                    $qrContent
                );               
            }

            return $this->responseHelper->createSuccessResponse($identity->toDomainProcessArray());
        } catch (\Exception $e) {
            $this->logger->critical(\json_encode($e->getMessage()));
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Mobile App
     * 
     * Find domain in the AccessRegistry by PublicId and domain name
     * Move into the AuthBridge table the related record by domainProcessId
     * Updated the Record with the credentials
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
        SharedService $sharedService,
        AuthBridgeService $authBridgeService
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_READ_STATE;
        $errorResponse = null;

        try {
            $processId = $sharedService->getProcessId($request, $payloadKey);

            if (!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $authBridgeService->getUserCredentialsByDomainProcessId($processId);

        } catch (\Exception $e) {
            $this->logger->critical('Error: ' . $e->getMessage());
            $errorResponse = $this->responseHelper->handleException($e, ['login_process_check' => false]);
        }

        return $errorResponse ?? $this->responseHelper->createSuccessResponse($response->toDomainStateArray());
    }
}
