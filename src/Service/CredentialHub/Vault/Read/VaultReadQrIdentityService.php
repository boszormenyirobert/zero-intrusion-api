<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\CredentialReadService;

class VaultReadQrIdentityService
{
    public function __construct(
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly CredentialReadService $credentialReadService
    ) {
    }

    public function handle(ExtensionCredentialRequestDTO $request): array
    {
        $identity = $this->credentialReadService->getVaultIdentity($request);       
        $qrCacheKey = $identity->getQrCacheKey();
        $qrContent = $this->processStateCacheService->get($qrCacheKey);

        $this->sharedNotificationService->sendFcmNotification('vaultRead', $request->userPublicId, $qrContent);

        $qrCode = $identity->toApplicationProcessArray();

        return $qrCode;
    }
}