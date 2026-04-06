<?php

namespace App\Controller\CredentialHub\OneTouch;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Helper\ResponseHelper;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Controller\CredentialHub\Shared\SharedRegistrationService;
use App\Controller\CredentialHub\SharedService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Controller\CredentialHub\PayloadKeys;
use App\Entity\AuthBridge;

/**
 * Class responsibility: mark the user the Desktop as "secure" machine for "oneTouchLogin"
 * used from browser extension
 */

#[Route('/api/credential-hub/one-touch')]
class OneTouchController extends AbstractController
{
    public function __construct(
        private PayloadValidator $payloadValidator,
        private LoggerInterface $logger,
        private ResponseHelper $responseHelper,
        private SharedRegistrationService $sharedRegistrationService,
        private AuthBridgeService $authBridgeService
    ) {}


    /**
     * Called by Browser-Extension
     */
    #[Route('/qr-identity', name: 'one_touch_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function oneTouchQrIdentity(
        Request $request,
        AuthBridgeService $authBridgeService,
        QrService $qrService,
        ValidatorInterface $validator,
        SharedService $sharedService
    ): JsonResponse {
        $processKey = 'oneTouchProcessId';
        $payloadKey = 'one_touch_qr_identity';

        try {
            $payload = $this->payloadValidator->validatePayload($request, $payloadKey);
            $validatedPayload = (object) $payload[$payloadKey];

            if (!isset($validatedPayload->type) || !$validatedPayload->type) {
                $this->logger->info('Missing registration type');
                return $this->responseHelper->createErrorResponse('Missing registration type');
            }

            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */
            $identity = $authBridgeService->generateRequestIdentity($processKey);

            $authToken = $identity->getXExtensionAuthOne();
            $processId = $identity->getOneTouchProcessId();;
            $qrContent = $this->sharedRegistrationService->getOneTouchQrContent($validatedPayload, $authToken, $processId);

            $errors = $validator->validate($qrContent);
            if (count($errors) > 0) {                
                foreach ($errors as $error) {
                    $this->logger->critical('sharedRegistrationQrIdentity: '.$error->getMessage() );                    
                }
            }           

            $qrCode = $qrService->getQrCode($qrContent);
            $identity->setQrCode($qrCode);

            return $this->responseHelper->createSuccessResponse($identity->toOneTouchProcessArray());
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Called by Mobile App
     * 
     * Get user PublicId and Email
     * Update the related record by oneTouchProcessId with the user PublicId and Email
      *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/identifier', name: 'one_touch_identifier', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function oneTouchIdentifier(
        Request $request,
        SharedService $sharedService
    ): Response {
        $payloadKey = PayloadKeys::ONE_TOUCH_IDENTIFIER;
       
        try {
            $process = $sharedService->getProcessId($request, $payloadKey, true); 

            if(!$process) {
                $this->logger->critical('Invalid or missing processId. Incoming from One Touch Identifier.');
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $result = $this->authBridgeService->persistDecryptedUserData($process);

            return $this->json([
                'one_touch_process' => $result,
                'error' => ''
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }  
    
    #[Route('/state', name: 'one_touch_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function oneTouchState(
        Request $request,
        AuthBridgeService $authBridgeService,
        SharedService $sharedService
    ): JsonResponse {
        $payloadKey = PayloadKeys::ONE_TOUCH_STATE;

        try {
            $processId = $sharedService->getProcessId($request, $payloadKey, false);

            if (!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $payload = $sharedService->pollTheRedisOneTouch($processId, 'oneTouchProcessId');

            return $this->responseHelper->createSuccessResponse(
                $payload
            );

        } catch (\Exception $e) {
            $this->logger->critical('Error: ' . $e->getMessage());
            return $this->responseHelper->handleException($e, ['login_process_check' => false]);            
        }        
    }   
}
