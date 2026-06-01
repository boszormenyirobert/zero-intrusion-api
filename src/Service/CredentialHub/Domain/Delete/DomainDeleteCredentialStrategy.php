<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Delete;

use App\Service\CredentialHub\Domain\Delete\DomainDeleteService;
use App\Service\CredentialHub\Shared\ReadCredentialStrategyInterface;

class DomainDeleteCredentialStrategy implements ReadCredentialStrategyInterface
{
    public function __construct(
        private readonly DomainDeleteService $domainDeleteService,
    ) {
    }

    public function handle(array $payload): bool
    {
        return (bool) $this->domainDeleteService->deleteDomain($payload);
    }
}
