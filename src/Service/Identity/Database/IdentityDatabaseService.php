<?php

declare(strict_types=1);

namespace App\Service\Identity\Database;

use App\Entity\Identity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class IdentityDatabaseService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function addIdentity(Identity $secret): void
    {
        $this->entityManager->persist($secret);
        $this->entityManager->flush();
    }

    public function updateIdentity(Identity $secret): void
    {
        $this->entityManager->persist($secret);
        $this->entityManager->flush();
    }
}
