<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use JsonException;

final class DeleteDomain
{
    public function __construct(
        private readonly AccessRegistryDomainService $accessRegistryDomainService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly ProcessStateCacheService $processStateCacheService
    ) {}

    public function handleDomainDeletion(array $process): ?bool
    {
        $processId = $process['sessionId'];

        $response = $this->accessRegistryDomainService->deleteDomainRegistraions($process);
        //$this->authBridgeService->updateProcessState('sessionId', $processId);
        $this->writeLoginEntryInRedis($processId, [
            'process' => true,
            'validation' => true,
            'process_check' => true,
            'success' => true,
        ]);

        return $response;
    }

    private function writeLoginEntryInRedis(string $processId, array $status): void
    {
        $this->processStateCacheService->set(
            $processId,
            $this->encodeJson($status, JSON_UNESCAPED_UNICODE),
            300
        );
    }

    private function encodeJson(array $payload, int $flags = 0): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | $flags);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON encoding failed.', 0, $exception);
        }
    }
}