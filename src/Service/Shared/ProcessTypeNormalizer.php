<?php

declare(strict_types=1);

namespace App\Service\Shared;

class ProcessTypeNormalizer
{
    public function resolveProcessId(?string $sessionId, ?string $domainProcessId): ?string
    {
        return $sessionId ?: $domainProcessId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function resolveProcessIdFromPayload(array $payload): ?string
    {
        $sessionId = isset($payload['sessionId']) && is_string($payload['sessionId'])
            ? $payload['sessionId']
            : null;

        $domainProcessId = isset($payload['domainProcessId']) && is_string($payload['domainProcessId'])
            ? $payload['domainProcessId']
            : null;

        return $this->resolveProcessId($sessionId, $domainProcessId);
    }

    public function isDomainLoginType(?string $type): bool
    {
        return $type === 'domain-login' || $type === 'system_hub_login';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function withLegacyProcessAliases(array $payload): array
    {
        $processId = $this->resolveProcessIdFromPayload($payload);

        if ($processId === null || $processId === '') {
            return $payload;
        }

        $payload['sessionId'] = $payload['sessionId'] ?? $processId;
        $payload['domainProcessId'] = $payload['domainProcessId'] ?? $processId;

        return $payload;
    }
}