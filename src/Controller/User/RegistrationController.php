<?php

/**
 * User registration/login on the HUB or on any registrated WEB site
 * 
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

namespace App\Controller\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Shared\RequestService;
use Psr\Log\LoggerInterface;
use App\Controller\User\UserService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use Exception;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Service\Firebase\FirebaseService;

#[Route('/api/user')]
class RegistrationController extends AbstractController
{

    public function __construct(
        private LoggerInterface $logger,
        private RequestService $requestService,
        private UserService $userService,
        private PayloadValidator $payloadValidator
    ) {
    }

    /**
     * Called during the user registration process to get the QR data
     * On the HUB the QR code will be generated with the received data
     * If the userPublicId is present in the payload, an FCM notification will be sent to the user device
     * 
     * The next steps—mobile call and extension/web polling—happen in the CredentialHub.
     */
    #[Route('/registration/qr-identity', name: 'user_registration_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function registrationQrIdentity(
        Request $request,
        ResponseHelper $responseHelper
    ) {
            $processKey = 'registrationProcessId';
            $payloadKey = 'user_registration';

            $data = $this->requestService->validPayload(json_decode($request->getContent(),true));

            try{
                $payload = $data[$payloadKey];
            }catch(Exception $e){
                $this->logger->critical('Invalid payload structure: ' . $e->getMessage());
                return $responseHelper->handleException($e);
            }

            $qrData = $this->userService->getQrData($payload, $processKey);     

        $defaultResponse = $qrData['defaultResponse'];            
        return new Response($defaultResponse['body'], 200, $defaultResponse['headers']);
    }  
}