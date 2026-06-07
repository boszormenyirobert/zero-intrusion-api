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
        $this->logger->info('Adding access registry for user: ' . json_encode($userData) . ' with type: ' . $type);
        $result = $this->accessRegistryDomainService->isAllowedUserDomainApplicationCombination($userData, $type);
        $this->logger->info('001 Result of combination check: ' . json_encode($result));
        $update = $userData['update'];
        if (!empty($result) && $result['newCombination'] === false && $update === 'new' && $zeroIntrusionRegistration === false) {
        //    return false;
        }

        return $this->addOrUpdateRegistry($result, $userData, $update, $type);
    }

    private function addOrUpdateRegistry(array $result, array $userData, string|bool $update, string $type): array
    {
        if (($update === 'new' || $update === false) && !array_key_exists('targetId', $userData)) {
            $userData['targetId'] = $this->getSubString(50);
        }

        if (array_key_exists('targetId', $userData)) {
            $this->accessRegistryDomainService->deleteDomainRegistraions($userData, $type);
        }

        return $this->accessRegistryDomainService->createDomain($userData, $type);
    }

    private function getSubString(int $length): string
    {
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
}