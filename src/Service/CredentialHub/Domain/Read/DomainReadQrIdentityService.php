<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\CredentialReadService;

class DomainReadQrIdentityService
{
    public function __construct(
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly CredentialReadService $credentialReadService
    ) {
    }

    public function handle(ExtensionCredentialRequestDTO $request): array
    {
        $identity = $this->credentialReadService->getDomainIdentity($request);         
        $qrCacheKey = $identity->getQrCacheKey();
        $qrContent = $this->processStateCacheService->get($qrCacheKey);        
                 
        $this->credentialReadService->handleNotification($request, $identity, $qrContent);      

        $qrCode = $identity->toProcessArray($identity->getType());

        return $qrCode;
    }
}