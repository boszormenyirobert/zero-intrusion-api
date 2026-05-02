<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use Symfony\Component\HttpFoundation\Request;

class VaultReadStateService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly SharedProcessPoller $sharedProcessPoller,
        private readonly AuthBridgeService $authBridgeService,
    ) {
    }

    public function handle(Request $request): ?array
    {
        $processId = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_READ_STATE);

        if (!$processId) {
            return null;
        }

        return $this->sharedProcessPoller->pollTheRedis($processId, $this->authBridgeService, 'application');
    }
}