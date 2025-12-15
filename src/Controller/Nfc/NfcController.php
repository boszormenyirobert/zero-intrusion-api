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
use App\Helper\UtilityHelper;
use App\Service\Crypters\SodiumService;

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
            
            $usersEncrypted = $this->identityRepository->findAll();
            $users = [];
            
            foreach ($usersEncrypted as $identity) {
                $this->logger->critical('NFC identity' . json_encode($identity));
                $decryptedUser = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($identity);

                /**
                 * TODO
                 * 
                 * Create from the [publicId, privateId, secret, credentialSecret] key => value an encrypted string 
                 * save the encryption key in the database, and send only the encrypted string to the Desktop Application
                 * Encrypted string will be written on the NFC-card
                 * Delete the credentialSecret from the database after NFC-card activation
                 * Secret has to be stored in the database, because it is for the secure communication between Handy Device and API
                 **/
                
                // $nfcEncryptionKey = UtilityHelper::generateKey('nfc'); // This key will be stored in the database for each user
                $nfcEncryptionKey = "MyTestEncryptionKey123"; // TODO remove test key

                $this->logger->critical('nfcEncryptionKey' . $nfcEncryptionKey);
                
                $rawUserData = [
                    'publicId' => $decryptedUser->getPublicId(),
                    'privateId' => $decryptedUser->getPrivateId(),
                    'secret' => $decryptedUser->getSecret(),
                    'credentialSecret' => $decryptedUser->getCredentialSecret()
                ];  
                
                try{
                    $stringRawUserData = json_encode($rawUserData, JSON_THROW_ON_ERROR);
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
        #[RequireJson]
        public function NfcDecryptCardData(
        Request $request
        ) {
            $payloadKey = PayloadKeys::API_NFC_DECRYPT;
            // Controll the request coming from the HUB and validate HMAC ~ Data integrity (shared secret HUB - API)
            $payload = $this->requestService->requestControll($request);
            // Get decrypted payload
            $validatedPayload = $this->requestService->validPayload($payload);
            // Access to the paylaoad by shared payloadKey
            $payloadArray = $validatedPayload[$payloadKey];
            
            $nfcEncryptionKey = "MyTestEncryptionKey123";

            // Compare payload corporatePublicId and the corporateAuthentication with the database records
            $this->logger->critical('NFC DECRYPT CALLED 1' . json_encode($payloadArray) );
            $this->logger->critical('NFC DECRYPT CALLED 2' . json_encode($payloadArray['nfcData']) );


            $stringRawUserData = json_encode($payloadArray['nfcData']['NfcData'], JSON_THROW_ON_ERROR);
            $decryptedUserData = $this->sodiumService->sodiumDecrypt($stringRawUserData, $nfcEncryptionKey);

            $this->logger->critical('Message ' . json_encode($decryptedUserData));

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
            $response = ['decryptedData' => $decryptedUserData];
            return $this->json(
                 $response
            );
        }
}