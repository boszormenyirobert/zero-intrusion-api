<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\Shared\ReadCredentialStrategyInterface;

class VaultReadCredentialStrategy implements ReadCredentialStrategyInterface
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
    ) {
    }

    public function handle(array $payload): bool
    {
        return $this->authBridgeService->persistDecryptedUserData($payload);
    }
}
