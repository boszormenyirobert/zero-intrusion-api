<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\Shared\ReadCredentialDecryptedOrchestrator;
use Symfony\Component\HttpFoundation\Request;

class DomainReadCredentialDecryptedService
{
    public function __construct(
        private readonly ReadCredentialDecryptedOrchestrator $orchestrator,
        private readonly DomainReadCredentialDecryptedStrategy $strategy,
    ) {
    }

    public function handle(Request $request): array
    {
        return $this->orchestrator->handle(
            $request,
            PayloadKeys::DOMAIN_READ_CREDENTIAL_ENCRYPTED,
            $this->strategy,
        );
    }
}