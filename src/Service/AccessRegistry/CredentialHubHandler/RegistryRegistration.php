<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\Service\AccessRegistry\AccessRegistryDomainService;
use Psr\Log\LoggerInterface;


final class RegistryRegistration
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AccessRegistryDomainService $accessRegistryDomainService
    ) {}

    public function addAccessRegistry(array $userData, string $type, bool $zeroIntrusionRegistration): array
    {
        $result = $this->accessRegistryDomainService->isAllowedUserDomainApplicationCombination($userData, $type);
        $update = $userData['update'];

        if (!empty($result) && $result['newCombination'] === false && $update === 'new' && $zeroIntrusionRegistration === false) {
        //    return false;
        }

        return $this->addOrUpdateRegistry($result, $userData, $update, $type);
    }

    private function addOrUpdateRegistry(array $result, array $userData, string $update, string $type): array
    {
        $this->logger->critical('Value of update: ' . $update);

        if ($update === 'new') {
            $userData['targetId'] = $this->getSubString(50);
        }

        if ($update === 'update') {
            $this->accessRegistryDomainService->deleteDomainRegistraions($userData);
        }

        return $this->accessRegistryDomainService->createDomain($userData, $type);
    }

    private function getSubString(int $length): string
    {
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
}