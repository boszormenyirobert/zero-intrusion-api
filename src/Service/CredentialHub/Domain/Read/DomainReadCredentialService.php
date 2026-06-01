<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\Shared\ReadCredentialOrchestrator;
use Symfony\Component\HttpFoundation\Request;

class DomainReadCredentialService
{
    public function __construct(
        private readonly ReadCredentialOrchestrator $orchestrator,
        private readonly DomainReadCredentialStrategy $strategy,
    ) {
    }

    public function handle(Request $request): bool
    {
        return $this->orchestrator->handle(
            $request,
            PayloadKeys::DOMAIN_READ_CREDENTIAL,
            $this->strategy,
        );
    }
}