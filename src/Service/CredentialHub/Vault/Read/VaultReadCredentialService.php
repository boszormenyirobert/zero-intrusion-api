<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Read\VaultReadCredentialResultDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class VaultReadCredentialService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly AuthBridgeService $authBridgeService,
    ) {
    }

    public function handle(Request $request): ?VaultReadCredentialResultDTO
    {
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_READ_CREDENTIAL, true);

        if (!$process) {
            return null;
        }

        return new VaultReadCredentialResultDTO(
            $this->authBridgeService->persistDecryptedUserData($process),
            '',
        );
    }
}