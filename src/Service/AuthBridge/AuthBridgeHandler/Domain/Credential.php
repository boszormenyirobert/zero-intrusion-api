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
                $decryptedLogin = $this->crypterDatabaseLoginService->decryptFromDatabase($user, 'domain');
              //  $this->loginDatabaseService->removeUserLogin($user);
                
                $mappedUserData = $this->mapUserData($decryptedLogin, $authBridgeResponse);
                array_push($authBridgeResponses, $mappedUserData);
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

    private function mapUserData($decryptedLogin, $authBridgeResponse): ResponseDTO
    {
        $authBridgeResponse->setCredential($decryptedLogin->getUserCredential());
        $authBridgeResponse->setDescription($decryptedLogin->getDescription()); 
        $authBridgeResponse->setUserPublicId($decryptedLogin->getPublicId());
        
        return $authBridgeResponse;
    }
}
