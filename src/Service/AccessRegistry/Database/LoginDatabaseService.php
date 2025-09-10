<?php

namespace App\Service\AccessRegistry\Database;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use Psr\Log\LoggerInterface;


class LoginDatabaseService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private AuthBridgeRepository $authBridgeRepository
    ) {
        $this->entityManager = $entityManager;
    }

    public function addUserLogin(AuthBridge $data):AuthBridge
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

    public function removeUserLogin($encryptedUser)
    {
        $this->entityManager->remove($encryptedUser);
        $this->entityManager->flush();
    }

    public function removeUserLogins(array $encryptedUsers)
    {
        foreach ($encryptedUsers as $user) {
            $this->entityManager->remove($user);
        }
        $this->entityManager->flush();
    }
}
