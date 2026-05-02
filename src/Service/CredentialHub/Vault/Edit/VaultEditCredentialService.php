<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Edit;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Edit\VaultEditCredentialResultDTO;
use App\Service\AccessRegistry\AccessRegistryVaultService;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class VaultEditCredentialService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly AccessRegistryVaultService $accessRegistryVaultService,
    ) {
    }

    public function handle(Request $request): ?VaultEditCredentialResultDTO
    {
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_EDIT_CREDENTIAL, true);

        if (!$process) {
            return null;
        }

        return new VaultEditCredentialResultDTO(
            $this->accessRegistryVaultService->editApplicationAccessRegistry($process),
            ''
        );
    }
}