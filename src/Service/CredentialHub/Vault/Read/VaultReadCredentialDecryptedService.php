<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\Shared\ReadCredentialDecryptedOrchestrator;
use Symfony\Component\HttpFoundation\Request;

class VaultReadCredentialDecryptedService
{
    public function __construct(
        private readonly ReadCredentialDecryptedOrchestrator $orchestrator,
        private readonly VaultReadCredentialDecryptedStrategy $strategy,
    ) {
    }

    public function handle(Request $request): array
    {
        return $this->orchestrator->handle(
            $request,
            PayloadKeys::VAULT_READ_CREDENTIAL_ENCRYPTED,
            $this->strategy,
        );
    }

    public function getApplicationCreadentials(string $userPublicId): array
    {
        return $this->strategy->getApplicationCreadentials($userPublicId);
    }
}