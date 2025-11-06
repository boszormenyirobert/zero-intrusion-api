<?php

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
class UserController extends AbstractController
{

    public function __construct(
        private LoggerInterface $logger,
        private RequestService $requestService,
        private UserService $userService,
        private PayloadValidator $payloadValidator
    ) {
    }

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
                return $responseHelper->handleException($e);
            }

            $qrData = $this->userService->getQrData($payload, $processKey);     

        $defaultResponse = $qrData['defaultResponse'];            
        return new Response($defaultResponse['body'], 200, $defaultResponse['headers']);
    }

    #[Route('/login/qr-identity', name: 'user_login_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function loginQrIdentity(
        Request $request,
        ResponseHelper $responseHelper,
        FirebaseService $firebaseService
    ) {
            $processKey = 'domainProcessId';
            $payloadKey = 'user_login';

            $data = $this->requestService->validPayload(json_decode($request->getContent(),true));
           
            try{
                $payload = $data[$payloadKey];
            }catch(Exception $e){
                return $responseHelper->handleException($e);
            }

            $qrData = $this->userService->getQrData($payload, $processKey);
            $userPublicId = $payload['userPublicId'];
            $this->logger->critical('userPublicId ', ['userPublicId' => $userPublicId]);
            if($userPublicId)
            {                
                $firebaseService->manageFcm(  
                    $userPublicId,                 
                    'Test Title', 
                    'Test Body',
                    $qrData['mobileResponse']
                );               
            }
            
            $defaultResponse = $qrData['defaultResponse'];     

        return new Response($defaultResponse['body'], 200, $defaultResponse['headers']);
    }    

    #[Route('/secure-device/qr-identity', name: 'secure_device_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function secureDeviceQrIdentity(
        Request $request,
        ResponseHelper $responseHelper
    ) {
            $processKey = 'domainProcessId';
            $payloadKey = 'secure_device_registration';

            $data = $this->requestService->validPayload(json_decode($request->getContent(),true));
           
            try{
                $payload = $data[$payloadKey];
            }catch(Exception $e){
                return $responseHelper->handleException($e);
            }

            $qrData = $this->userService->getQrData($payload, $processKey);
            
            $defaultResponse = $qrData['defaultResponse'];     
            $this->logger->critical('body', $defaultResponse);

        return new Response($defaultResponse['body'], 200, $defaultResponse['headers']);
    }        
}