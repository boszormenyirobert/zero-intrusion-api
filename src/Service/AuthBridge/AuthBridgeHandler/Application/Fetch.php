<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Repository\AuthBridgeRepository;
use Psr\Log\LoggerInterface;
use App\Service\Crypters\CrypterDatabaseLoginService;
use Exception;
use Symfony\Component\Serializer\SerializerInterface;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryState;
use App\Entity\AuthBridge;
use App\Service\Cache\ProcessStateCacheService;
use App\DTO\CredentialHub\ResponseDTO;

class Fetch
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private LoggerInterface $logger,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private SerializerInterface $serializerInterface,
        private RegistryState $registryState,
        private ProcessStateCacheService $processStateCacheService
    ) {}

    public function fetchForOneTouch($oneTouchProcessId, $processType): AuthBridge|false
    {        
        // $user = $this->authBridgeRepository->findOneBy([$processType => $oneTouchProcessId]);
        $cachedValue = $this->processStateCacheService->get($oneTouchProcessId);
        if(!$cachedValue){
            return false;
        }

        $authBridge = new AuthBridge();
        return $authBridge->fromCacheArray(json_decode($cachedValue, true));
    }   

    public function fetchFromAccessTable($applicationProcessId, $processType): array
    {
        $process = $processType === 'application' ? 'applicationProcessId' : 'domainProcessId';
    //    $encryptedUserFromDatabase = $this->authBridgeRepository->findOneBy([$process => $applicationProcessId]);
        $cachedValue = $this->processStateCacheService->get($applicationProcessId);
        $encryptedUser = null;

        if (is_string($cachedValue) && $cachedValue !== '') {
            $cachedPayload = json_decode($cachedValue, true);

            if (is_array($cachedPayload)) {
                $encryptedUser = AuthBridge::fromCacheArray($cachedPayload);
            }
        }

        if (!$encryptedUser instanceof AuthBridge) {
            $this->logger->critical("return missing handy validation");
            $process = new ResponseDTO(false, false, false);
            return [
                'process' => $process->toDomainStateArray(),
                'response' => false,
            ];
        }
 
        // TODO => Rename column "applications" to "credentials" => This store the list of credentials by domain or a user application credentials
        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabase($encryptedUser, "applications");
        $process = new ResponseDTO(true, true, true);
       
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
        $this->logger->critical("Mapping application data for database storage application !!! :" . json_encode($a));
        return [            
            'application' => $a->application,
            'userCredential' => $a->decrypted,
            'description' => $a->description,
            'targetId' => $a->targetId
        ];
    }
    private function mapDomain(object $a): array
    {
        return [
            'credential' => $a->userCredential,
            'description' => $a->description,
            'targetId' => $a->targetId
        ];
    }    
}
