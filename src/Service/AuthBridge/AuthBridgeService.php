<?php

namespace App\Service\AuthBridge;

use App\Service\AuthBridge\AuthBridgeHandler\AuthBridgeHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Identity;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Credential;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Fetch;
use App\DTO\CredentialHub\ResponseDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\Entity\AuthBridge;

class AuthBridgeService
{
    public function __construct(
        private Identity $identity,
        private Credential $credential,
        private AuthBridgeHandler $authBridgeHandler,
        private Fetch $fetch
    ) {}


    // Copy user from AccessRegistry into AuthBridge table
    // domainProcessId is already in the table. Added by the browser-extension-identity creation
    // This function will update the authBridge table with the user credential|application by the domainProcessId/applicationProcessId
    public function persistDecryptedUserData(array $user): bool
    {
        return $this->authBridgeHandler->persistDecryptedUserData($user);
    }
    
    public function getDecryptedUserData(array $user): bool
    {
        return $this->authBridgeHandler->getDecryptedUserData($user);
    }

    public function getDecryptedUserDataToMobileRequest(array $user): array
    {
        return $this->authBridgeHandler->getDecryptedUserDataToMobileRequest($user);
    }    

    public function persistDecryptedUserDataForWeb(array $user): array
    {
        return $this->authBridgeHandler->persistDecryptedUserDataForWeb($user);
    }

    public function persistOneTouchUserData(array $user): bool
    {
        return $this->authBridgeHandler->persistOneTouchUserData($user);
    }

    // deprecated
    public function getUserCredentialsByDomainProcessId($domainProcessId): array
    {
        return $this->credential->getUserCredentialsByDomainProcessId($domainProcessId);
    }

    public function generateRequestIdentity(string $processType): CredentialHubIdentityDTO
    {
        return $this->identity->generateRequestIdentity($processType);
    }

    public function fetchFromAccessTable($applicationProcessId, $processType): array
    {
        return $this->fetch->fetchFromAccessTable($applicationProcessId, $processType);
    }

    public function fetchForOneTouch($oneTouchProcessId, $processType): AuthBridge|false
    {
        return $this->fetch->fetchForOneTouch($oneTouchProcessId, $processType);
    }

    public function updateProcessState(string $processKey, string $processId): void
    {
        $this->authBridgeHandler->updateProcessState($processKey, $processId);
    }
}
