<?php

namespace App\Service\AccessRegistry;

use Doctrine\ORM\EntityManagerInterface;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Psr\Log\LoggerInterface;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;

class AccessRegistryVaultService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private LoggerInterface $logger,
        private ProcessStateCacheService $processStateCacheService,
        private ResolverService $resolverService,
        private AuthBridgeService $authBridgeService
    ) {}

    public function editApplicationAccessRegistry(array $userData)
    {
        $processKey = 'registrationProcessId';
        $processId = $userData['registrationProcessId'];

        $this->resolverService->getDelete()->deleteAccessRegistry($userData['targetId']);
       // $this->authBridgeService->updateProcessState($processKey, $processId);

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

    
    private function writeLoginEntryInRedis(string $processId, array $status) {       
        $this->processStateCacheService->set(
            $processId,
            json_encode($status, JSON_UNESCAPED_UNICODE),
            300
        );
    }
}