<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Edit;

use App\Service\CredentialHub\Shared\SharedRegistrationService;
use App\Service\CredentialHub\Vault\Edit\VaultEditService;
use App\DTO\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;

class VaultEditQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly VaultEditService $vaultEditService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly SharedRegistrationService $sharedRegistrationService,
    ) {
    }

    public function handle(VaultEditQrIdentityRequestDTO $request): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('registrationProcessId');
        $payload = $request->toObject();

        $this->sharedRegistrationService->saveUserCredentialInAuthBridge($payload, $identity->getRegistrationProcessId());

        $qrContent = $this->vaultEditService->getQrContent($payload, $identity->getXExtensionAuthOne(), $identity->getRegistrationProcessId());
        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        if ($request->userPublicId !== null && $request->userPublicId !== '') {
            $this->sharedNotificationService->sendFcmNotification('vaultEdit', $request->userPublicId, $qrContent);
        }

        return $identity->toRegistrationProcessArray();
    }
}