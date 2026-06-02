<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Service\Cache\ProcessStateCacheService;

class ReadCredentialCacheResolver
{
    public function __construct(
        private readonly ProcessStateCacheService $processStateCacheService,
    ) {
    }

    public function resolve(array $user): array|false
    {
        if (
            array_key_exists('credentialCacheKey', $user)
            && array_key_exists('qrCacheKey', $user)
            && ($user['credentialCacheKey'] !== $user['qrCacheKey'])
        ) {
            $response = $this->processStateCacheService->get($user['credentialCacheKey'] ?? 'missing');

            if (!is_array($response)) {
                return false;
            }

            return [
                'credentials' => $response,
                'validation' => true,
            ];
        }

        return false;
    }
}