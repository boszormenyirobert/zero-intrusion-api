<?php

namespace App\Service\Identity\Database;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Identity;
use Psr\Log\LoggerInterface;

class IdentityDatabaseService
{

    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {}

    public function addIdentity(Identity $secret)
    {
        $this->entityManager->persist($secret);
        $this->entityManager->flush();
    }

    public function updateIdentity(Identity $secret)
    {
        $this->entityManager->persist($secret);
        $this->entityManager->flush();
    }
}
