<?php

/**
 * NFC => Communication with the Desktop Applicaiton
 * 
 * All communication through the HUB
 * 
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

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
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;

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
        private CorporateIdentityRepository $corporateIdentityRepository,
        private IdentityRepository $identityRepository,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService
    ) {}

    #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function getNfcUsers(
        Request $request
        ) {

            $payloadKey = PayloadKeys::API_NFC_USERS;
            // Controll the request coming from the HUB and validate HMAC ~ Data integrity (shared secret HUB - API)
            $payload = $this->requestService->requestControll($request);
            // Get decrypted payload
            $validatedPayload = $this->requestService->validPayload($payload);
            // Access to the paylaoad by shared payloadKey
            $payloadArray = $validatedPayload[$payloadKey];

            // Compare payload corporatePublicId and the corporateAuthentication with the database records
            $corporateId = $payloadArray['publicId'];
            $corporateIdKey = $payloadArray['message'];

            $corporateId = $this->corporateIdentityRepository->findOneBy([
                'corporateId' =>  $corporateId,
                'corporateIdKey' =>  $corporateIdKey
            ]);

            if (!$corporateId) {
                $this->logger->critical('NFC USERS CALLED - CORPORATE ID NOT FOUND ' . json_encode($payloadArray));

                return $this->json(
                    $this->responseHelper->createErrorResponse('NFC Users 001: Corporate identity not found.', 404)
                );
            }
            
            // TODO : Fetch NFC users from database
            $usersEncrypted = $this->identityRepository->findAll();
            $users = [];
            
            foreach ($usersEncrypted as $identity) {
                $this->logger->critical('NFC identity' . json_encode($identity));
                $decryptedUser = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($identity);
                $users[] = [
                    'email' => $decryptedUser->getEmail(),
                    'publicId' => $decryptedUser->getPublicId(),
                    'privateId' => $decryptedUser->getPrivateId(),
                    'secret' => $decryptedUser->getSecret(),
                    'credentialSecret' => $decryptedUser->getCredentialSecret()              
                ];
            }


            $this->logger->critical('NFC USERS CALLED 2' . json_encode($users));

            $response = ['users' => $users];

            return $this->json(
                 $response
            );
        }
}