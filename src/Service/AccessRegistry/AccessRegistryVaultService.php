<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry;

use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Psr\Log\LoggerInterface;

class AccessRegistryVaultService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private readonly LoggerInterface $logger,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly ResolverService $resolverService,
        private readonly AuthBridgeService $authBridgeService
    ) {}

    public function editApplicationAccessRegistry(array $userData): void
    {
        $processId = $userData['registrationProcessId'];

        $this->resolverService->getDelete()->deleteAccessRegistry($userData['targetId']);
       // $this->authBridgeService->updateProcessState('registrationProcessId', $processId);

        // Create new
        $userData['registrationState'] = true;
        // Add user data to the accessRegistry table
        $userData = $this->crypterDatabaseAccessRegistryService->encyptDataObject($userData, 'application'); // 'application' => 'update-applications
        $this->entityManager->persist($userData);
        $this->entityManager->flush();
        $this->logger->critical($processId);

        $this->writeLoginEntryInRedis($processId, [
            'process' => true,
            'validation' => true,
            'process_check' => true,
            'success' => true,
        ]);
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