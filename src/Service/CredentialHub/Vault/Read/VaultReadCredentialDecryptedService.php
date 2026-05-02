<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class VaultReadCredentialDecryptedService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly VaultReadService $vaultReadService,
    ) {
    }

    public function handle(Request $request): array
    {
        $user = $this->sharedPayloadService->getPayload($request, PayloadKeys::VAULT_READ_CREDENTIAL_ENCRYPTED);

        return ['credentials' => $this->vaultReadService->getDecryptedCredentials($user['publicId'])];
    }
}