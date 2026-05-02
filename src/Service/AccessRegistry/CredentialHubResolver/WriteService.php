<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use Psr\Log\LoggerInterface;

final class WriteService
{
    public function __construct(
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly Encryptor $encryptor
    ) {}

    public function createAccessRegistryDomain(array $userData, string $type): AccessRegistry
    {
        $userData['registrationState'] = true;
        $userData = $this->crypterDatabaseAccessRegistryService->encryptDataObjectOrFail($userData, $type);
        
        $this->entityManager->persist($userData);
        $this->entityManager->flush();

        return $userData;
    }
}