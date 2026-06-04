<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\Payload\JsonPayloadDecoder;
use JsonException;
use Symfony\Component\HttpFoundation\Request;

class SharedRegistrationNewToEncryptService
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function handle(Request $request): bool
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::SHARED_REGISTRATION_NEW_TO_ENCRYPT);
        $user = $this->jsonPayloadDecoder->requireArray(
            $validatedPayload[PayloadKeys::SHARED_REGISTRATION_NEW_TO_ENCRYPT] ?? null,
            'Invalid shared registration new-to-encrypt payload.'
        );

        $sessionId = $user['sessionId'] ?? null;
        if (!is_string($sessionId) || $sessionId === '') {
            throw new \InvalidArgumentException('Invalid or missing sessionId.');
        }

        $type = $user['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new \InvalidArgumentException('Invalid or missing type.');
        }
        if ($type !== 'new-user-credential') {
            throw new \InvalidArgumentException('Invalid type. Expected new-user-credential.');
        }

        $source = $user['source'] ?? 'extension';
        if (!is_string($source) || $source === '') {
            throw new \InvalidArgumentException('Invalid source value.');
        }

        $publicKey = $user['publicKey'] ?? null;
        if (!is_string($publicKey) || $publicKey === '') {
            throw new \InvalidArgumentException('Invalid or missing publicKey.');
        }

        $userPublicId = $user['userPublicId'] ?? null;
        if (!is_string($userPublicId) || $userPublicId === '') {
            throw new \InvalidArgumentException('Invalid or missing userPublicId.');
        }

        try {
            $cacheValue = json_encode(['publicKey' => $publicKey], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Failed to encode shared registration cache payload.', 0, $exception);
        }

        $this->processStateCacheService->set($sessionId, $cacheValue);
        $this->processStateCacheService->set(sprintf('%s_userPublicId', $sessionId), $userPublicId);

        return true;
    }
}