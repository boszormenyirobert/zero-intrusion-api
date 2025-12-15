<?php

namespace App\Controller\Nfc;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Cookie;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\DTO\RegistrationProcessDTO;
use Symfony\Component\HttpFoundation\Response;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use App\Service\JWT\JwtService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;

class NfcController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private JWTEncoderInterface $jwtEncoder,
        private UserService $userService,
        private JwtService $jwtService,
        private UserRepository $userRepository,
        private PayloadValidator $payloadValidator 
    ) {}

    #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function getNfcUsers(
        Request $request,
        JwtService $jwtService
        ) {

            $payloadKey = PayloadKeys::API_NFC_USERS;

            $this->logger->critical('NFC USERS CALLED 1');
           $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);

            $this->logger->critical('NFC USERS CALLED 2' . json_encode($validatedPayload));
            $this->logger->critical('NFC USERS CALLED 3');
            return ['users' => ['3boszormenyirobert@yahoo.com','3vilagteteje@freemail.hu']];

            $headers =  $request->headers->all();

            $corporateIentification = json_decode($request->getContent(), true);       

            $process = "api_nfc_users"; 

            $corporateIentification['hmac'] = $headers['x-client-auth'];

            $response = $this->userService->getNfcUsers($process, $corporateIentification);

            return $this->json($response);
        }
}