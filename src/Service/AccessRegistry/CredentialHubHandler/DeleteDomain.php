<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;

final class DeleteDomain
{
    public function __construct(
        private AccessRegistryDomainService $accessRegistryDomainService,
        private AuthBridgeService $authBridgeService,
        private ProcessStateCacheService $processStateCacheService
    ) {}

    public function handleDomainDeletion($process): bool|null
    {
        $processKey = 'removeProcessId';
        $processId = $process['removeProcessId'];

        $response = $this->accessRegistryDomainService->deleteDomainRegistraions($process);
        //$this->authBridgeService->updateProcessState($processKey, $processId);
        $this->writeLoginEntryInRedis($processId, [
            'process' => true,
            'validation' => true,
            'process_check' => true,
            'success' => true,
        ]);

        return $response;
    }

    private function writeLoginEntryInRedis(string $processId, array $status) {       
        $this->processStateCacheService->set(
            $processId,
            json_encode($status, JSON_UNESCAPED_UNICODE),
            300
        );
    }
}