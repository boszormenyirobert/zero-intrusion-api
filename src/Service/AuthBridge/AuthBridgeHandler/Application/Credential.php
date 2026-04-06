<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor;
use Psr\Log\LoggerInterface;
use App\Service\Cache\ProcessStateCacheService;
use App\Entity\AuthBridge;

class Credential
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private LoginDatabaseService $loginDatabaseService,
        private LoggerInterface $logger,
        private Encryptor $encryptor,
        private ProcessStateCacheService $processStateCacheService
    ) {}

    public function setDecryptedValuesForApplication(array $user): bool
    {
        $apps = $user['credentials'];
        $decryptedCredentials = $this->getDecryptedCredentials($apps);
        $state = false;
        
        $authBridge = $this->authBridgeRepository->findOneBy([
            'applicationProcessId' => $user['applicationProcessId']
        ]);

        if($authBridge){
            $encrypted = $this->encryptor->encrypt($decryptedCredentials, base64_decode($authBridge->getIv()));
            $authBridge->setApplications($encrypted);
            $authBridge->setProcessState(true);

           //$this->loginDatabaseService->addUserLogin($process);

            $this->writeLoginEntryInRedis($user['applicationProcessId'], $authBridge);
            $state = true;
        }

        return $state;
    }
    
    private function writeLoginEntryInRedis(string $processId, $authBridge) {       
        $this->processStateCacheService->set(
            $processId,
            json_encode($authBridge->toCacheArray(), JSON_UNESCAPED_UNICODE),
            300
        );
    }

        /**
     * Convert a $process object to an array
     * @param object $process
     * @return array
     */
    public function processToArray($process): array
    {
        if (is_null($process)) {
            return [];
        }
        // If the process object has a toArray method, use it
        if (method_exists($process, 'toArray')) {
            return $process->toArray();
        }
        // Otherwise, get public properties
        return get_object_vars($process);
    }

    private function getDecryptedCredentials(array $apps): array
    {
        $decryptedCredentials = [];
        foreach ($apps as $app) {
            $decryptedCredentials[] = [
                'decrypted' => $app['credential'],
                'description' => $app['description'],
                'targetId' => $app['targetId'],
                'application' => $app['application']
            ];
        }
        return $decryptedCredentials;
    }
}

