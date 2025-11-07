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

    public function fetchApplicationsFromAccessTable($applicationProcessId, $processType): array
    {
        $process = $processType === 'application' ? 'applicationProcessId' : 'domainProcessId';
        $encryptedUser = $this->authBridgeRepository->findOneBy([$process => $applicationProcessId]);


        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabase($encryptedUser, "applications");

        $process = $this->registryState->registrationState($applicationProcessId, $process);
        
        return [
            'process' => $process->toVaultStateArray(),
            'response' => $decrypted ? $this->buildResponseFromApplications($decrypted->getApplications(), $processType) : false
        ];        
    }

    private function buildResponseFromApplications(string $json, string $processType): array
    {
        try {
            $apps = json_decode($json);
            if($processType === 'application') {
                return array_map(fn($a) => $this->mapApplication($a, $processType), $apps);
            } else {
               return array_map(fn($a) => $this->mapDomain($a, $processType), $apps);
            }                   
        } catch (Exception $e) {
            $this->logger->critical("Error: " . $this->serializerInterface->serialize($e, 'json'));
            return ['error' => 'Failed to process application data'];
        }
    }

    // convert json object to array
    private function mapApplication(object $a): array
    {
        return [
            'application' => $a->application,
            'userCredential' => $a->userCredential,
            'description' => $a->description,
            'targetId' => $a->targetId
        ];
    }
    private function mapDomain(object $a): array
    {
        $this->logger->critical("Mapping domain credential", (array)$a  );
        return [
            'userName' => $a->userName,
            'userPassword' => $a->userPassword,
            'description' => $a->description,
            'targetId' => $a->targetId
        ];
    }    
}
