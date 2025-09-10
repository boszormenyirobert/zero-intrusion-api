<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AuthBridge\AuthBridgeService;

final class DeleteDomain
{
    public function __construct(
        private AccessRegistryDomainService $accessRegistryDomainService,
        private AuthBridgeService $authBridgeService
    ) {}

    public function handleDomainDeletion($process): bool|null
    {
        $processKey = 'removeProcessId';
        $processId = $process['removeProcessId'];

        $response = $this->accessRegistryDomainService->deleteDomainRegistraions($process);
        $this->authBridgeService->updateProcessState($processKey, $processId);

        return $response;
    }
}