<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\Shared\ReadCredentialOrchestrator;
use Symfony\Component\HttpFoundation\Request;

class VaultReadCredentialService
{
    public function __construct(
        private readonly ReadCredentialOrchestrator $orchestrator,
        private readonly VaultReadCredentialStrategy $strategy,
    ) {
    }

    public function handle(Request $request): bool
    {
        return $this->orchestrator->handle(
            $request,
            PayloadKeys::VAULT_READ_CREDENTIAL,
            $this->strategy,
        );
    }
}