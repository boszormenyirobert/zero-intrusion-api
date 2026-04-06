<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AccessRegistry\DTO\DeleteApplicationDto;
use App\Service\Cache\ProcessStateCacheService;

final class DeleteApplication
{
    public function __construct(
        private AccessRegistryDomainService $accessRegistryDomainService,
        private ResolverService $resolverService,
        private AuthBridgeService $authBridgeService,
        private ProcessStateCacheService $processStateCacheService
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

    private function writeLoginEntryInRedis(string $processId, array $status) {       
        $this->processStateCacheService->set(
            $processId,
            json_encode($status, JSON_UNESCAPED_UNICODE),
            300
        );
    }
}