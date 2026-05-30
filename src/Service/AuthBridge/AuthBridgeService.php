<?php

declare(strict_types=1);

namespace App\Service\AuthBridge;

use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use App\Entity\AuthBridge;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Fetch;
use App\Service\AuthBridge\AuthBridgeHandler\AuthBridgeHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Credential;
use App\Service\AuthBridge\AuthBridgeHandler\Identity;

class AuthBridgeService
{
    public function __construct(
        private readonly Identity $identity,
        private readonly Credential $credential,
        private readonly AuthBridgeHandler $authBridgeHandler,
        private readonly Fetch $fetch
    ) {}


    // Copy user from AccessRegistry into AuthBridge table
    // domainProcessId is already in the table. Added by the browser-extension-identity creation
    // This function will update the authBridge table with the user credential|application by the domainProcessId/sessionId
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

    public function persistDecryptedUserDataForWeb(array $user): ?array
    {
        return $this->authBridgeHandler->persistDecryptedUserDataForWeb($user);
    }

    public function persistOneTouchUserData(array $user): bool
    {
        return $this->authBridgeHandler->persistOneTouchUserData($user);
    }

    // deprecated
    public function getUserCredentialsByDomainProcessId(string $domainProcessId): array
    {
        return $this->credential->getUserCredentialsByDomainProcessId($domainProcessId);
    }

    public function generateRequestIdentity(string $processType): ExtensionCredentialResponseDTO
    {
        return $this->identity->generateRequestIdentity($processType);
    }

    public function fetchFromAccessTable(string $sessionId, string $processType): array
    {
        return $this->fetch->fetchFromAccessTable($sessionId, $processType);
    }

    public function fetchForOneTouch(string $sessionId, string $processType): AuthBridge|false
    {
        return $this->fetch->fetchForOneTouch($sessionId, $processType);
    }

    public function updateProcessState(string $processKey, string $processId): void
    {
        $this->authBridgeHandler->updateProcessState($processKey, $processId);
    }

    public function saveUserCredentialInAuthBridge(mixed $userCredential, string $registrationProcessId): bool
    {
        return $this->authBridgeHandler->saveUserCredentialInAuthBridge($userCredential, $registrationProcessId);
    }

    public function getUserCredentialFromAuthBridge(string $processId): ?string
    {
        return $this->authBridgeHandler->getUserCredentialFromAuthBridge($processId);
    }
}
