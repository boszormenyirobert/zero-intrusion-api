<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Domain;

use App\Repository\AuthBridgeRepository;
use Psr\Log\LoggerInterface;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\DTO\CredentialHub\ResponseDTO;

class Credential
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private LoggerInterface $logger,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private LoginDatabaseService $loginDatabaseService
    ) {}

    /**
     * Retrieves user credentials by domainProcessId.
     *
     * @param string $domainProcessId
     * @return ResponseDTO[]
     */
    public function getUserCredentialsByDomainProcessId($domainProcessId): array
    {
        $authBridges = $this->findValidUser($domainProcessId);
        $authBridgeResponses = [];
        
        foreach ($authBridges as $authBridgeResponse) {
            $user = $authBridgeResponse->getData();
           
            if ($authBridgeResponse->isProcessCheck()) {
                // changed default 'domain' to 'application'
                $decryptedLogin = $this->crypterDatabaseLoginService->decryptFromDatabase($user, 'applications');
              //  $this->loginDatabaseService->removeUserLogin($user);

                if ($decryptedLogin && $decryptedLogin->getApplications()) {
                    $credentialsArray = json_decode($decryptedLogin->getApplications(), true);
                    $mappedUserDataCollection = $this->mapUserData($credentialsArray, $authBridgeResponse);
                    
                    // Add all mapped credentials to the response array
                    foreach ($mappedUserDataCollection as $mappedUserData) {
                        array_push($authBridgeResponses, $mappedUserData);
                    }
                }
            }                            
            //$authBridgeResponse->setData(null);
            //array_push($authBridgeResponses, $authBridgeResponse);
        }
        return $authBridgeResponses;
    }

    /**
    * @return ResponseDTO[]
    */
       private function findValidUser(string $domainProcessId): array
    {
        $authBridges = $this->authBridgeRepository->findBy(
            ['domainProcessId' => $domainProcessId],
            ['createdAt' => 'DESC']
        );
       
        $userCredentialsByDomain = [];
        foreach ($authBridges as $authBridge) {
            $responseDTO = new ResponseDTO(
                true,
                !$authBridge->isProcessState() ? 'Missing handy validation' : true,
                $authBridge->isProcessState() ? true : false,
                $authBridge
            );
            array_push($userCredentialsByDomain, $responseDTO);
        }

        return $userCredentialsByDomain;
     }   

    private function mapUserData(array $decryptedCredentials, $authBridgeResponse): array
    {
        $mappedResponses = [];
        
        foreach ($decryptedCredentials as $credential) {
            $clonedResponse = clone $authBridgeResponse;
            
            // Parse the JSON credential data
            $credentialData = json_decode($credential['decrypted'], true);
            
            $clonedResponse->setCredential(json_encode($credentialData));
            $clonedResponse->setDescription($credential['description']); 
            $clonedResponse->setUserPublicId($authBridgeResponse->getData()->getPublicId());
            
            $mappedResponses[] = $clonedResponse;
        }
        
        return $mappedResponses;
    }
}
