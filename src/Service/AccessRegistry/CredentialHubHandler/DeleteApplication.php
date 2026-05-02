<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AccessRegistry\DTO\DeleteApplicationDto;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use JsonException;

final class DeleteApplication
{
    public function __construct(
        private readonly AccessRegistryDomainService $accessRegistryDomainService,
        private readonly ResolverService $resolverService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly ProcessStateCacheService $processStateCacheService
    ) {}

    public function deleteApplication(DeleteApplicationDto $dto): array
    {
        $processKey = 'removeProcessId';

        $deletedFromRegistry = $this->resolverService->getDelete()->deleteAccessRegistry($dto->targetId);
        $processState = $this->authBridgeService->updateProcessState($processKey, $dto->removeProcessId);
        
        $this->writeLoginEntryInRedis($dto->removeProcessId, [
            'process' => true,
            'validation' => true,
            'process_check' => true,
            'success' => true,
        ]);

        return [
            'deletedFromRegistry' => $deletedFromRegistry ? '' : 'Application not found or already deleted',
            'processState' => $processState
        ];
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