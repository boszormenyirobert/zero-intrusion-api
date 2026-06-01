<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\Shared\ReadCredentialOrchestrator;
use Symfony\Component\HttpFoundation\Request;

class DomainDeleteCredentialService
{
    public function __construct(
        private readonly ReadCredentialOrchestrator $orchestrator,
        private readonly DomainDeleteCredentialStrategy $strategy,
    ) {
    }

    public function handle(Request $request): bool
    {
        return $this->orchestrator->handle(
            $request,
            PayloadKeys::DOMAIN_DELETE_CREDENTIAL,
            $this->strategy,
        );
    }
}