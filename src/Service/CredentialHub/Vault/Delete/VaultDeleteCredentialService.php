<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\Vault\Delete\VaultDeleteService;
use App\DTO\CredentialHub\Vault\Delete\VaultDeleteCredentialResultDTO;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class VaultDeleteCredentialService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly VaultDeleteService $vaultDeleteService,
    ) {
    }

    public function handle(Request $request): ?VaultDeleteCredentialResultDTO
    {
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_DELETE_CREDENTIAL, true);

        if (!$process) {
            return null;
        }

        $response = $this->vaultDeleteService->deleteApplication($process);

        return new VaultDeleteCredentialResultDTO(
            $response['processState'],
            $response['deletedFromRegistry'] ? null : 'Application not found or already deleted',
        );
    }
}