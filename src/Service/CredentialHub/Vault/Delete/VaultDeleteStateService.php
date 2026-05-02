<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use Symfony\Component\HttpFoundation\Request;

class VaultDeleteStateService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly SharedProcessPoller $sharedProcessPoller,
    ) {
    }

    public function handle(Request $request): ?array
    {
        $processId = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_DELETE_STATE);

        if (!$processId) {
            return null;
        }

        return $this->sharedProcessPoller->pollTheRedisDefault($processId) ?? [];
    }
}