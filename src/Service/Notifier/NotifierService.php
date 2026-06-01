<?php

namespace App\Service\Notifier;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Service\Crypters\CrypterDatabaseService;

final class NotifierService
{
    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient,
        private CorporateIdentityRepository $corporateIdentityRepository,
        private IdentityRepository $identityRepository,
        private CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
        private Encryptor $encryptor,
        private ContainerBagInterface $params,
        private CrypterDatabaseService $crypterDatabaseService
    ) {}

    public function callBackUserRegistration($registratedUser, $user)
    {
        $encryptedCorporate = $this->corporateIdentityRepository->findOneBy([
            'corporateId' => $user['corporateId']
        ]);

        $encryptedIdentity = $this->identityRepository->findOneBy([
            'publicId' => $registratedUser['publicId']
        ]);

        $decryptedIdentity = $this->crypterDatabaseIdentityService->decryptFromDatabase($encryptedIdentity);
        //$encryptedUserCredential = $this->encryptor->findDecryptedCredentialForWeb($user, $decryptedIdentity->getSecret());
        //$user['userAuth'] = $encryptedUserCredential['decrypted'];

        $corporateIdentity = $this->crypterDatabaseService->decryptFromDatabase($encryptedCorporate);

        // sanitized the response !!!
         $userIdentity = json_encode([                    
            'publicId' => $user['publicId'],
            'email' => $user['email'],
          //  'userAuth' => $user['userAuth']
        ]);

        $userIdentity = json_encode([
            'signature' => $this->signMessageWithPrivateKey($userIdentity, $corporateIdentity),            
            'publicId' => $user['publicId'],
            'email' => $user['email'],
            'registrationProcessId' => $user['registrationProcessId'],
         //   'userAuth' => $user['userAuth']
        ]);

        $callbackPath = $encryptedCorporate->getCallbackUserRegistration();
        $this->logger->critical(' Callback Path: ' . $callbackPath);    
        $this->logger->critical(' userIdentity : ' . $userIdentity);    

        try {
            $userIdentity = ['json' => json_decode($userIdentity, true)];
        } catch (\Exception $e) {
            $this->logger->critical(' JSON Decode Error: ' . $e->getMessage());    
            return;
        }   

        $response =  $this->httpClient->request(
            'POST', 
            $callbackPath, 
            $userIdentity
        );

        $this->logger->critical(' Callback Path: ' . $response->getStatusCode());    
    }

    public function callBackUserLogin($decryptedResponse, $user)
    {
        $encryptedCorporate = $this->corporateIdentityRepository->findOneBy([
            'corporateId' => $user['corporateId']
        ]);
        $corporateIdentity = $this->crypterDatabaseService->decryptFromDatabase($encryptedCorporate);

        $userIdentity = json_encode([
            'publicId' => $user['publicId'],
            'email' => $user['email'],
        //    'userAuth' =>  $decryptedResponse['decrypted']
        ]);

        $userIdentitySigned =[
            'signature' => $this->signMessageWithPrivateKey($userIdentity, $corporateIdentity),
            'publicId' => $user['publicId'],
            'email' => $user['email'],
            'processId' => $user['sessionId'] ?? ($user['domainProcessId'] ?? null),
        //    'userAuth' =>  $decryptedResponse['decrypted']
        ];

        $this->httpClient->request(
            'POST', 
            $corporateIdentity->getCallbackUserLogin(), 
            ['json' => $userIdentitySigned]
        );
    }

    private function signMessageWithPrivateKey($userIdentity, $corporate)
    {
        $corporatePrivateKey = $corporate->getSslPrivateKey();
        $result = openssl_sign($userIdentity, $signature, $corporatePrivateKey, OPENSSL_ALGO_SHA256);

        if (!$result) {
            $error = openssl_error_string();
            $this->logger->critical('openssl_sign failed: ' . $error);
            return null; 
        }

        return base64_encode($signature);
    }
}