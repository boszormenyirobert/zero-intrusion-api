<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\Cache\ProcessStateCacheService;
use JsonException;
use Psr\Log\LoggerInterface;

class Credential
{
    public function __construct(
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly LoginDatabaseService $loginDatabaseService,
        private readonly LoggerInterface $logger,
        private readonly Encryptor $encryptor,
        private readonly ProcessStateCacheService $processStateCacheService
    ) {}

    public function setDecryptedValuesForApplication(array $user): bool
    {
        $authBridge = $this->authBridgeRepository->findOneBy([
            'applicationProcessId' => $user['applicationProcessId']
        ]);

        if (!$authBridge instanceof AuthBridge) {
            return false;
        }

        $encrypted = $this->encryptor->encrypt(
            $this->getDecryptedCredentials($user['credentials']),
            base64_decode((string) $authBridge->getIv())
        );

        $authBridge->setApplications($encrypted);
        $authBridge->setProcessState(true);

        //$this->loginDatabaseService->addUserLogin($process);

        $this->writeLoginEntryInRedis($user['applicationProcessId'], $authBridge);

        return true;
    }
    
    private function writeLoginEntryInRedis(string $processId, AuthBridge $authBridge): void
    {
        $this->processStateCacheService->set(
            $processId,
            $this->encodeJson($authBridge->toCacheArray(), JSON_UNESCAPED_UNICODE),
            300
        );
    }

        /**
     * Convert a $process object to an array
     * @param object $process
     * @return array
     */
    public function processToArray(mixed $process): array
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

    private function encodeJson(array $payload, int $flags = 0): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | $flags);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON encoding failed.', 0, $exception);
        }
    }
}

