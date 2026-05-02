<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry;

use App\DTO\CredentialHub\ResponseDTO;
use App\Service\AccessRegistry\CredentialHubHandler\CredentialHubHandler;
use App\Service\Notifier\NotifierService;

class AccessRegistryRegistrationService
{
    public function __construct(
        private readonly CredentialHubHandler $credentialHubHandler,
        private readonly NotifierService $notifierService
    ) {}


    public function addAccessRegistry(array $userData, string $type, bool $zeroIntrusionRegistration): array
    {
        return $this->credentialHubHandler->getRegistration()->addAccessRegistry($userData, $type, $zeroIntrusionRegistration);
    }

    public function setRegistrationState(array $user, bool $state): array
    {
        return $this->credentialHubHandler->getStateHandler()->setRegistrationState($user, $state);
    }

    public function getState(string $processId, string $key): ResponseDTO
    {
        return $this->credentialHubHandler->getStateHandler()->registrationState($processId, $key);
    }

    public function sendNotification(array $registratedUser, array $user): void
    {
        $this->notifierService->callBackUserRegistration($registratedUser, $user);
    }
}