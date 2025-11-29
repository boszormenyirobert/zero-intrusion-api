<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor;
use App\Service\AuthBridge\AuthBridgeHandler\Application\ListBuilder;
use Psr\Log\LoggerInterface;

class Credential
{
    public function __construct(

        private AuthBridgeRepository $authBridgeRepository,
        private LoginDatabaseService $loginDatabaseService,
        private ListBuilder $listBuilder,
        private LoggerInterface $logger,
        private Encryptor $encryptor,
    ) {}

    public function setDecryptedValuesForApplication(array $user, string $userSecret): bool
    {
        $apps = $user['credentials'];
        $decryptedCredentials = [];
        foreach ($apps as $app) {
            $decryptedCredentials[] = [
                'decrypted' => $app['credential'],
                'description' => $app['description'],
                'targetId' => $app['targetId']
            ];
        }

        $state = false;
        // deprecated
        // $userApplicationList = $this->listBuilder->buildDecryptedApplicationList($user['publicId'], $userSecret);
        $this->logger->critical("Setting decrypted values for application process ID: " . json_encode($user));
        
        
        $process = $this->authBridgeRepository->findOneBy([
            'applicationProcessId' => $user['applicationProcessId']
        ]);

        if($process){
            $encrypted = $this->encryptor->encrypt($decryptedCredentials, base64_decode($process->getIv()));
            $process->setApplications($encrypted);
            $process->setProcessState(true);

            $this->loginDatabaseService->addUserLogin($process);
            $state = true;
        }
        $this->logger->critical("Setting decrypted values for application process state: " . json_encode($state));

        return $state;
    }
}

