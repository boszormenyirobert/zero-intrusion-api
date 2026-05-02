<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;

class VaultReadQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly VaultReadService $vaultReadService,
        private readonly SharedNotificationService $sharedNotificationService,
    ) {
    }

    public function handle(VaultReadQrIdentityRequestDTO $request): array
    {
        if ($request->source !== 'extension' || $request->type !== 'applications') {
            throw new \RuntimeException('Identity generation failed');
        }

        $identity = $this->authBridgeService->generateRequestIdentity('applicationProcessId');
        $qrContent = $this->vaultReadService->getQrContent($request->type, $request->source, $identity->getXExtensionAuthOne(), $identity);
        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        if ($request->userPublicId !== null && $request->userPublicId !== '') {
            $this->sharedNotificationService->sendFcmNotification('vaultRead', $request->userPublicId, $qrContent);
        }

        return $identity->toApplicationProcessArray();
    }
}