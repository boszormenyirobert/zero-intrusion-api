<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Repository\AuthBridgeRepository;
use Psr\Log\LoggerInterface;
use App\Service\Crypters\CrypterDatabaseLoginService;
use Exception;
use Symfony\Component\Serializer\SerializerInterface;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryState;

class Fetch
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private LoggerInterface $logger,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private SerializerInterface $serializerInterface,
        private RegistryState $registryState
    ) {}

    public function fetchApplicationsFromAccessTable($applicationProcessId): array
    {
        $encryptedUser = $this->authBridgeRepository->findOneBy(['applicationProcessId' => $applicationProcessId]);

        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabase($encryptedUser, "applications");

        $process = $this->registryState->registrationState($applicationProcessId, 'applicationProcessId');
        
        return [
            'process' => $process->toVaultStateArray(),
            'response' => $decrypted ? $this->buildResponseFromApplications($decrypted->getApplications()) : false
        ];        
    }

    private function buildResponseFromApplications(string $json): array
    {
        try {
            $apps = json_decode($json);
            return array_map(fn($a) => $this->mapApplication($a), $apps);
        } catch (Exception $e) {
            $this->logger->critical("Error: " . $this->serializerInterface->serialize($e, 'json'));
            return ['error' => 'Failed to process application data'];
        }
    }

    private function mapApplication(object $a): array
    {
        return [
            'application' => $a->application,
            'userCredential' => $a->userCredential,
            'description' => $a->description,
            'targetId' => $a->targetId
        ];
    }
}
