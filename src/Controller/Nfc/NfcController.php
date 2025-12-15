<?php

namespace App\Controller\Nfc;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use App\Controller\PayloadValidator\PayloadValidator;
use Symfony\Component\HttpFoundation\Request;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Service\Shared\RequestService;
use App\Helper\ResponseHelper;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\HttpFoundation\Cookie;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\DTO\RegistrationProcessDTO;
use Symfony\Component\HttpFoundation\Response;



class NfcController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService,
        private PayloadValidator $payloadValidator ,
        private RequestService $requestService,
        private ResponseHelper $responseHelper,
    ) {}

    #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function getNfcUsers(
        Request $request
        ) {

            $payloadKey = PayloadKeys::API_NFC_USERS;

            $this->logger->critical('NFC USERS CALLED 1');
            $payload = $this->requestService->requestControll($request);
            $validatedPayload = $this->requestService->validPayload($payload);
            $this->logger->critical('NFC USERS CALLED 1.1');
            $payloadArray = $validatedPayload[$payloadKey];


            $this->logger->critical('NFC USERS CALLED 2' . json_encode($payloadArray));
            $this->logger->critical('NFC USERS CALLED 3');

            $response = ['users' => ['boszormenyirobert@yahoo.com']];

            return $this->json(
                 $response
            );
        }
}