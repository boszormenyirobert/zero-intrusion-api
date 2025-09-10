<?php

namespace App\Service\Restore\Database;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Restore;
use Psr\Log\LoggerInterface;

class RestoreDatabaseService
{

    public function __construct(
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager
    ) {}

    public function addRestore(Restore $device)
    {
        $this->entityManager->persist($device);
        $this->entityManager->flush();
    }
}
