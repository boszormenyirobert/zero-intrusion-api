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
     * @return ResponseDTO
     */
    public function getUserCredentialsByDomainProcessId($domainProcessId): ResponseDTO
    {
        $authBridgeResponse = $this->findValidUser($domainProcessId);
        $user = $authBridgeResponse->getData();

        if ($authBridgeResponse->isProcessCheck()) {
            $decryptedLogin = $this->crypterDatabaseLoginService->decryptFromDatabase($user, 'domain');
            $this->loginDatabaseService->removeUserLogin($user);
            
            return $this->mapUserData($decryptedLogin, $authBridgeResponse);
        }
        $authBridgeResponse->setData(null);

        return $authBridgeResponse;
    }

       private function findValidUser(string $domainProcessId): ResponseDTO
    {
        $authBridge = $this->authBridgeRepository->findOneBy(
            ['domainProcessId' => $domainProcessId],
            ['createdAt' => 'DESC']
        );
       
        return new ResponseDTO(
            $authBridge ? true : false,
            ($authBridge && !$authBridge->isProcessState()) ? 'Missing handy validation' : true,
            ($authBridge && $authBridge->isProcessState()) ? true : false,
            $authBridge ? $authBridge : null
        );
     }   

    private function mapUserData($decryptedLogin, $authBridgeResponse): ResponseDTO
    {
        $authBridgeResponse->setCredential($decryptedLogin->getUserCredential());
        $authBridgeResponse->setDescription($decryptedLogin->getDescription()); 

        return $authBridgeResponse;
    }
}
