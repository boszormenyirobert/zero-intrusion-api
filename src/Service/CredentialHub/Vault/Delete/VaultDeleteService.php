<?php

namespace App\Service\CredentialHub\Vault\Delete;

use App\Service\AccessRegistry\CredentialHubHandler\DeleteApplication;
use App\Service\CredentialHub\SharedPayloadService;

class VaultDeleteService
{
    public function __construct(
        private DeleteApplication $deleteApplicationHandler,
        private SharedPayloadService $sharedPayloadService,
    ) {}

    public function deleteApplication($process): array
    {
        $deleteApplicationDto = $this->sharedPayloadService->getApplicationDto($process);

        return $this->deleteApplicationHandler->deleteApplication($deleteApplicationDto);
    }
}