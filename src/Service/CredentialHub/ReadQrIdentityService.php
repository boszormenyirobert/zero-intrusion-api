<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\DTO\CredentialHub\QrContentDTO;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\CredentialReadService;

class ReadQrIdentityService
{
    public function __construct(
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly CredentialReadService $credentialReadService
    ) {
    }

    public function handle(ExtensionCredentialRequestDTO $request, IdentityType $type): array
    {
        $identity = $this->credentialReadService->getIdentity($request, $type);       
        $qrCacheKey = $identity->getQrCacheKey();
        $qrContent = $this->processStateCacheService->get($qrCacheKey);

        if (!$qrContent instanceof QrContentDTO) {
            throw new \RuntimeException(sprintf('Missing or invalid QR content in cache for key: %s', (string) $qrCacheKey));
        }

        $this->credentialReadService->handleNotification($request, $identity, $type, $qrContent);

        $qrCode = $identity->toProcessArray($type->value);

        return $qrCode;
    }
}