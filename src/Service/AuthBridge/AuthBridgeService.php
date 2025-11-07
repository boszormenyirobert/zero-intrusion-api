<?php

namespace App\Service\AuthBridge;

use App\Service\AuthBridge\AuthBridgeHandler\AuthBridgeHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Identity;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Credential;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Fetch;
use App\DTO\CredentialHub\ResponseDTO;
use App\DTO\QR\CredentialHubIdentityDTO;

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

    public function persistDecryptedUserDataForWeb(array $user): array
    {
        return $this->authBridgeHandler->persistDecryptedUserDataForWeb($user);
    }    

    // Called by extension checker: DomainRead
    public function getUserCredentialsByDomainProcessId($domainProcessId): array
    {
        return $this->credential->getUserCredentialsByDomainProcessId($domainProcessId);
    }

    /**
     * Generates a new tokenHash and secret for the browser-extension
     * Saved in the AuthBridge Database
     *
     * @return array An associative array containing the generated keys: publicId, privateId, and secret.
     */
    #public function getBrowserExtensionIdentity($processType): array
    #{
    #    return $this->identity->getBrowserExtensionIdentity($processType);
    #}

    public function generateRequestIdentity(string $processType): CredentialHubIdentityDTO
    {
        return $this->identity->generateRequestIdentity($processType);
    }

    public function fetchApplicationsFromAccessTable($applicationProcessId, $processType): array
    {
        return $this->fetch->fetchApplicationsFromAccessTable($applicationProcessId, $processType);
    }

    public function updateProcessState(string $processKey, string $processId): void
    {
        $this->authBridgeHandler->updateProcessState($processKey, $processId);
    }
}
