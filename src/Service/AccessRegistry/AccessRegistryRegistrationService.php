<?php

namespace App\Service\AccessRegistry;

use App\Service\AccessRegistry\CredentialHubHandler\CredentialHubHandler;
use App\DTO\CredentialHub\ResponseDTO;
use App\Service\Notifier\NotifierService;

class AccessRegistryRegistrationService
{
    public function __construct(
        private CredentialHubHandler $credentialHubHandler,
        private NotifierService $notifierService
    ) {}


    public function addAccessRegistry(array $userData, $type, $zeroIntrusionRegistration)
    {
        return $this->credentialHubHandler->getRegistration()->addAccessRegistry($userData, $type, $zeroIntrusionRegistration);
    }

    public function setRegistrationState(array $user, bool $state): array
    {
        return $this->credentialHubHandler->getStateHandler()->setRegistrationState($user, $state);
    }

    public function getState($processId, $key): ResponseDTO
    {
        return $this->credentialHubHandler->getStateHandler()->registrationState($processId, $key);
    }

    public function sendNotification($registratedUser, $user){
        $this->notifierService->callBackUserRegistration($registratedUser, $user);
    }
}