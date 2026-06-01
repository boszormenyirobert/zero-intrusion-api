<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class ReadCredentialDecryptedOrchestrator
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly ReadCredentialCacheResolver $readCredentialCacheResolver,
        private readonly ProcessStateCacheService $processStateCacheService,
    ) {
    }

    public function handle(
        Request $request,
        string $payloadKey,
        ReadCredentialDecryptedStrategyInterface $strategy,
    ): array {
        $user = $this->sharedPayloadService->getPayload($request, $payloadKey);
        $cached = $this->readCredentialCacheResolver->resolve($user);

        if ($cached !== false) {
            return $cached;
        }

        $storedQrData = $this->objectToArrayRecursive(
            $this->processStateCacheService->get($user['qrCacheKey'] ?? 'missing')
        );

        if (!is_array($storedQrData)) {
            $storedQrData = [];
        }

        $context = array_merge($storedQrData, $user);

        return $strategy->resolve($context);
    }

    private function objectToArrayRecursive(mixed $data): mixed
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            return array_map([$this, 'objectToArrayRecursive'], $data);
        }

        return $data;
    }
}
