<?php

/**
 * NFC => Communication with the Desktop Applicaiton
 * 
 * All communication through the HUB
 * 
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

namespace App\Controller\DeviceManagement\Nfc;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\User\UserService;
use Psr\Log\LoggerInterface;
use App\Controller\PayloadValidator\PayloadValidator;
use Symfony\Component\HttpFoundation\Request;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Attribute\DesktopHmac;
use App\Controller\CredentialHub\PayloadKeys;
use App\Service\Shared\RequestService;
use App\Helper\ResponseHelper;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Helper\UtilityHelper;
use App\Service\Crypters\SodiumService;
use App\Entity\CorprateIdentity;

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
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private UtilityHelper $utilityHelper,
        private SodiumService $sodiumService
    ) {}

        /**
         * Used by the Desktop Application to fetch all NFC users
         * On the Desktop Application the encrypted NFC data will be written on the NFC card by the selected user
         */
        #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
        #[RequireHmac]
        #[DesktopHmac]
        #[RequireJson]
        public function getNfcUsers(Request $request) 
        {
            // Request controlled by HMAC => Desktop HMAC and JSON Attribute
            $usersEncrypted = $this->identityRepository->findAll();
            $users = [];
            
            foreach ($usersEncrypted as $identity) {
                $decryptedUser = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($identity);

                $nfcEncryptionKey = "MyTestEncryptionKey123"; // TODO remove test key
                
                $rawUserData = [
                    'publicId' => $decryptedUser->getPublicId(),
                    'privateId' => $decryptedUser->getPrivateId(),
                    'secret' => $decryptedUser->getSecret(),
                    'credentialSecret' => $decryptedUser->getCredentialSecret()
                ];  
                try{
                    $stringRawUserData = json_encode($rawUserData, JSON_THROW_ON_ERROR);
                    $this->logger->critical('NFC USERS RAW DATA ' . $stringRawUserData);
                    $encryptedUserData = $this->sodiumService->sodiumEncrypt($stringRawUserData, $nfcEncryptionKey);
                    
                    $users[] = [
                        'email' => $decryptedUser->getEmail(),
                        'nfcData' => $encryptedUserData            
                    ];
                }catch(\Exception $e){
                    $this->logger->critical('NFC USERS ENCRYPTION ERROR ' . $e->getMessage());  
                }
            }           

            $response = ['users' => $users];

            return $this->json(
                 $response
            );
        }

        #[Route('/api/nfc/decrypt', name: 'api_nfc_decrypt', methods: "POST")]
        #[RequireHmac]
        #[DesktopHmac]
        #[RequireJson]
        public function NfcDecryptCardData(Request $request) 
        {
            $payloadKey = PayloadKeys::API_NFC_DECRYPT;
            // Controll the request coming from the HUB and validate HMAC ~ Data integrity (shared secret HUB - API)
            $payload = $this->requestService->requestControll($request);
            // Get decrypted payload
            $validatedPayload = $this->requestService->validPayload($payload);
            // Access to the paylaoad by shared payloadKey
            $payloadArray = $validatedPayload[$payloadKey];
            
            $nfcEncryptionKey = "MyTestEncryptionKey123";
            $nfcEmail = null;
            $nfcEncryptedData = null;

            $this->logger->critical('NFC Encrypt Payload ' . json_encode($payloadArray['nfcData']));

            $decryptedUserDataJson = $this->sodiumService->sodiumDecrypt($payloadArray['nfcData'], $nfcEncryptionKey);
            $payload = json_decode($decryptedUserDataJson, true);

            return $this->json(
                  $payload
            );
        }
}