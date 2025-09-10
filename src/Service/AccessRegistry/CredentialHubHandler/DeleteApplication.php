<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AccessRegistry\DTO\DeleteApplicationDto;

final class DeleteApplication
{
    public function __construct(
        private AccessRegistryDomainService $accessRegistryDomainService,
        private ResolverService $resolverService,
        private AuthBridgeService $authBridgeService
    ) {}

    public function deleteApplication(DeleteApplicationDto $dto): array
    {
        $processKey = 'removeProcessId';

        $deletedFromRegistry = $this->resolverService->getDelete()->deleteAccessRegistry($dto->targetId);
        $processState = $this->authBridgeService->updateProcessState($processKey, $dto->removeProcessId);

        return [
            'deletedFromRegistry' => $deletedFromRegistry ? '' : 'Application not found or already deleted',
            'processState' => $processState
        ];
    }
}