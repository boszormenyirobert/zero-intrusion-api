<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\Database;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LoginDatabaseService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly AuthBridgeRepository $authBridgeRepository
    ) {}

    public function addUserLogin(AuthBridge $data): AuthBridge
    {
        try {
            $this->entityManager->persist($data);
            $this->entityManager->flush();

            return $data;
        } catch (\Exception $e) {
            // Handle transaction start failure
            $this->logger->critical('extensionValidCommunication: ' . $e->getMessage());

            throw new \RuntimeException('Failed to start transaction: ' . $e->getMessage());
        }
    }

    public function removeUserLogin(AuthBridge $encryptedUser): void
    {
        $this->entityManager->remove($encryptedUser);
        $this->entityManager->flush();
    }

    public function removeUserLogins(array $encryptedUsers): void
    {
        foreach ($encryptedUsers as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }
}
