<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Service\CredentialHub\Shared\ReadCredentialStrategyInterface;

class DomainReadCredentialStrategy implements ReadCredentialStrategyInterface
{
    public function __construct(
        private readonly DomainService $domainService,
    ) {
    }

    public function handle(array $payload): bool
    {
        return $this->domainService->setByUserSignedCredentialsInCache($payload);
    }
}
