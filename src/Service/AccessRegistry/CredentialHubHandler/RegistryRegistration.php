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

    public function addAccessRegistry(array $userData, string $type): array
    {
        $result = $this->accessRegistryDomainService->isAllowedUserDomainApplicationCombination($userData, $type);
        $update = $userData['update'];

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