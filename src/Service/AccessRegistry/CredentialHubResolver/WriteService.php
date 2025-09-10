<?php

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;

final class WriteService
{

    public function __construct(
        private CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private Encryptor $encryptor
    ) {}

    public function createAccessRegistryDomain(array $userData, string $type): AccessRegistry
    {
        $userData['registrationState'] = true;
        $userData = $this->crypterDatabaseAccessRegistryService->encyptDataObject($userData, $type);
        
        $this->entityManager->persist($userData);
        $this->entityManager->flush();

        return $userData;
    }
}