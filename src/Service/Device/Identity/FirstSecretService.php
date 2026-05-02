<?php

declare(strict_types=1);

namespace App\Service\Device\Identity;

use App\Service\Identity\IdentityService;

class FirstSecretService
{
    public function __construct(
        private readonly IdentityService $identityService,
    ) {
    }

    public function handle(): array
    {
        return $this->identityService->getKey()->toIdentityArray();
    }
}
