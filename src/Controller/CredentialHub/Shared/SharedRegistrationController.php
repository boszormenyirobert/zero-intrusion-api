<?php

namespace App\Controller\CredentialHub\Shared;

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

/**
 * Flow: 
 * 1. Extension push user-credentials
 * 2. /qr-identity 
 *  - Generate processId and authToken, save the user-credential in the database with the processId
 * 3. /new/to-encrypt
 *  - Mobile application pull the "unencrypted" user-credential with the processId, encrypt with the mobile app and return to the mobile application
 * 4. /new
 * - Mobile application push the "encrypted" user-credential with the processId, save the "encrypted" user-credential in the database with the processId
 * 5. /state
 * - Extension pull the registration state with the processId, return the registration state to the extension
 */

#[Route('/api/credential-hub/shared/registration')]
class SharedRegistrationController extends AbstractController
{

    public function __construct(
        private PayloadValidator $payloadValidator,
        private LoggerInterface $logger,
        private ResponseHelper $responseHelper,
        private SharedRegistrationService $sharedRegistrationService
    ) {}

    /* Called by Browser-Extension 
     *   
     * TODO : MISSING X_AUTH TIME_LIMIT => EXTEND THE FIRST HMACH-hash
     *
     * This is used to create a browser extension registration identity
     * Return 2 HMAC-hashed values:
     * Timestamp added to the second HMAC-hash, which is used by the browser-extension
     * 
     * It did not saved in the database. Secret and Message from .env settings
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/qr-identity', name: 'shared_registration_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function sharedRegistrationQrIdentity(
        Request $request,
        AuthBridgeService $authBridgeService,
        QrService $qrService,
        ValidatorInterface $validator,
        SharedService $sharedService
    ): JsonResponse {
        $processKey = 'registrationProcessId';
        $payloadKey = 'shared_registration_qr_identity';

        try {
            $validatedPayload = json_decode(
                $this->payloadValidator->validatePayload($request, $payloadKey)[$payloadKey] ?? '',
                false
            );
            if (!isset($validatedPayload->type)) {
                return $this->responseHelper->createErrorResponse('Missing registration type');
            }
            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */
            $identity = $authBridgeService->generateRequestIdentity($processKey);
            $authToken = $identity->getXExtensionAuthOne();
            $processId = $identity->getRegistrationProcessId();;

            // Save the user credential in the database with the processId             
            $this->sharedRegistrationService->saveUserCredentialInAuthBridge($validatedPayload, $identity->getRegistrationProcessId());
            // return the qr content with the processId and authToken to the extension and mobile app
            $qrContent = $this->sharedRegistrationService->getQrContent($validatedPayload, $authToken, $processId);
            $errors = $validator->validate($qrContent);

            if (count($errors) > 0) {                
                foreach ($errors as $error) {
                    $this->logger->critical('sharedRegistrationQrIdentity: '.$error->getMessage() );                    
                }
            }           

            $extendedQrContent = $this->sharedRegistrationService->getExtendedQrContent($validatedPayload->type, $qrContent, $validatedPayload);
            $qrCode = $qrService->getQrCode($extendedQrContent);
            $identity->setQrCode($qrCode);
            
            if(isset($validatedPayload->userPublicId) && $validatedPayload->userPublicId)
            {     
                $sharedService->sendFcmNotification(
                    'sharedRegistration',
                    $validatedPayload->userPublicId,
                    $qrContent
                );  
            }            

            return $this->responseHelper->createSuccessResponse($identity->toRegistrationProcessArray());
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /* Called by Mobile App
     *
     * Mobile Identity and HMAC Registration Hash retrived from the request (first HMAC-hash)
     * Retrive domain or application-registration data from the AuthBridge Database to encrypt with the mobile app
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/new/to-encrypt', name: 'shared_registration_new_to_encrypt', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function sharedRegistrationNewToEncrypt(
        Request $request,
        AccessRegistryRegistrationService $accessService
    ): Response {
        try {           
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new_to_encrypt');
            $user = json_decode($validatedPayload['shared_registration_new_to_encrypt'], true);
            $type = $user['type']; // registration-domain, application-registration, system_hub_registration
            $key = in_array($type, ['registration-domain', 'system_hub_registration']) ? 'domain' : 'application';
            $userCredential = $this->sharedRegistrationService->getUserCredentialFromAuthBridge($user['registrationProcessId']);            

            return $this->json([
                'registration_process_init' => $userCredential,
                'error' => ''
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /* Called by Mobile App
     *
     * TODO : MISSING X_AUTH TIME_LIMIT => it is in domainProcessId => Prevent registration, do not write in DB with old hash !!!
     * 
     * Mobile Identity and HMAC Registration Hash retrived from the request (first HMAC-hash)
     * Add domain or application-registration data to the AccessRegistry Database
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/new', name: 'shared_registration_new', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function sharedRegistrationNew(
        Request $request,
        AccessRegistryRegistrationService $accessService
    ): Response {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new');
            $user = json_decode($validatedPayload['shared_registration_new'], true);
            $type = $user['type'];

            $key = in_array($type, ['registration-domain', 'system_hub_registration']) ? 'domain' : 'application';
            $registratedUser = $accessService->addAccessRegistry($user, $key, $type == 'system_hub_registration' ? true : false);

            if($type == 'system_hub_registration'){
                $accessService->sendNotification($registratedUser, $user);
            }

            return $this->json([
                'registration_process_one' => $registratedUser,
                'error' => ''
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Checks the registration state of the browser extension.
     *
     * Called by the browser extension. Expects a HMAC-signed JSON POST request
     * containing a `processId` under the key `shared_registration_state`.
     *
     * Authenticated via the `X-Extension-Auth` header. The header is valid
     * for a maximum of 3 minutes (time-limited HMAC).
     *
     * @param Request $request
     * @param AccessRegistryRegistrationService $userRegistrationService
     * @return JsonResponse
     */
    #[Route('/state', name: 'shared_registration_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function sharedRegistrationState(
        Request $request,
        AccessRegistryRegistrationService $accessService,
        SharedService $sharedService
    ): JsonResponse {
        $payloadKey = 'shared_registration_state';

        try {
            $processId = $sharedService->getProcessId($request,  $payloadKey);

            if(!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }
            
            //$response = $accessService->getState($processId, 'registrationProcessId');
            //$response = $sharedService->getChacheByProcessId($processId);
            $response = $sharedService->pollTheRedisDefault($processId);

            return $this->responseHelper->createSuccessResponse($response);
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e, [
                'registration_process_check' => 'error'
            ]);
        }
    }
}
