<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Repository\AuthBridgeRepository;
use App\DTO\CredentialHub\ResponseDTO;

final class RegistryState
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function setRegistrationState(array $user, bool $state): array
    {
        $user['registrationState'] = $state;
        return $user;
    }

    public function registrationState($processId, $key): ResponseDTO
    {
        $state = false;
        $process = $this->authBridgeRepository->findOneBy([
            $key => $processId
        ]);

        if ($process && $process->isProcessState()) {
            $this->entityManager->remove($process);
            $this->entityManager->flush();
            $state = true;
        }

        return new ResponseDTO(
            $process === null ? false : true,
           // ($process && !$process->isProcessState()) ? 'Missing handy validation' : true,
           true,
            $state
        );
    }
}
