<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\CredentialReadService;

class ReadQrIdentityService
{
    public function __construct(
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly CredentialReadService $credentialReadService
    ) {
    }

    public function handle(ExtensionCredentialRequestDTO $request, string $type): array
    {
        $identity = $this->credentialReadService->getIdentity($request, $type);       
        $qrCacheKey = $identity->getQrCacheKey();
        $qrContent = $this->processStateCacheService->get($qrCacheKey);

        $this->credentialReadService->handleNotification($request, $identity, $qrContent);      

        $qrCode = $identity->toProcessArray($identity->getType());

        return $qrCode;
    }
}