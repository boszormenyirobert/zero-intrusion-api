<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedRequestIdentityService;
use Symfony\Component\HttpFoundation\Request;

class VaultDeleteQrIdentityService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly SharedRequestIdentityService $sharedRequestIdentityService,
        private readonly SharedNotificationService $sharedNotificationService,
    ) {
    }

    public function handle(Request $request): ?array
    {
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_DELETE_QR_IDENTITY, true);

        if (!$process) {
            return null;
        }

        $identity = $this->sharedRequestIdentityService->generateRequestIdentity($process, PayloadKeys::VAULT_DELETE_PROCESS_ID);

        if (isset($process['userPublicId'], $identity['toNotification']) && $process['userPublicId'] && $identity['toNotification']) {
            $this->sharedNotificationService->sendFcmNotification('vaultDelete', $process['userPublicId'], $identity['toNotification']);
        }

        return (array) $identity['toQrRead'];
    }
}