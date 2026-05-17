<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use Symfony\Component\HttpFoundation\Request;

class DomainReadStateService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly SharedProcessPoller $sharedProcessPoller,
        private readonly AuthBridgeService $authBridgeService,
    ) {
    }

    public function handle(Request $request): ?array
    {
        $processId = $this->sharedPayloadService->getProcessId($request, PayloadKeys::DOMAIN_READ_STATE);

        if (!$processId) {
            return null;
        }

        $fullResponse = $this->sharedProcessPoller->pollTheRedisDefault($processId);
        
        
        return $this->sanitizeResponse($fullResponse['cache'] ?? []);
        // return $fullResponse['cache'] ?? [];
    }

    private function sanitizeResponse(array $payload): array
    {   
        unset($payload['process']);
        unset($payload['validation']);
        unset($payload['process_check']);
        unset($payload['success']);
        unset($payload['domain']);
        unset($payload['domainProcessId']);
        unset($payload['source']);
        unset($payload['publicKey']);
        unset($payload['qrCacheKey']);
        unset($payload['publicId']);
        unset($payload['privateId']);
        unset($payload['update']);
        unset($payload['type']);
        unset($payload['email']);


        return $payload;    
    }
}