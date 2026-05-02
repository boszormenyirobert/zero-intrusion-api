<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubHandler;

use App\DTO\CredentialHub\ResponseDTO;
use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class RegistryState
{
    public function __construct(
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function setRegistrationState(array $user, bool $state): array
    {
        $user['registrationState'] = $state;

        return $user;
    }

    public function registrationState(string $processId, string $key): ResponseDTO
    {
        $process = $this->authBridgeRepository->findOneBy([
            $key => $processId
        ]);

        if (!$process instanceof AuthBridge) {
            return new ResponseDTO(false, true, false);
        }

        if ($process->isProcessState()) {
            $this->entityManager->remove($process);
            $this->entityManager->flush();

            return new ResponseDTO(true, true, true);
        }

        return new ResponseDTO(true, true, false);
    }
}
