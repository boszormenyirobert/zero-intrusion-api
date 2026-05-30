<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\DTO\AuthBridge\Application\FetchFromAccessTableResultDTO;
use App\DTO\CredentialHub\ResponseDTO;
use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryState;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\Crypters\CrypterDatabaseLoginService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

class Fetch
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private LoggerInterface $logger,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private SerializerInterface $serializerInterface,
        private RegistryState $registryState,
        private ProcessStateCacheService $processStateCacheService
    ) {
    }

    public function fetchForOneTouch(string $oneTouchProcessId, string $processType): AuthBridge|false
    {
        $cachedValue = $this->processStateCacheService->get($oneTouchProcessId);

        if (!is_string($cachedValue) || $cachedValue === '') {
            return false;
        }

        $cachedPayload = $this->decodeCachePayload($cachedValue);

        return is_array($cachedPayload) ? AuthBridge::fromCacheArray($cachedPayload) : false;
    }

    public function fetchFromAccessTable(string $sessionId, string $processType): array
    {
        return $this->fetchFromAccessTableOrFail($sessionId, $processType)->toArray();
    }

    public function fetchFromAccessTableOrFail(string $sessionId, string $processType): FetchFromAccessTableResultDTO
    {
        $cachedValue = $this->processStateCacheService->get($sessionId);
        $encryptedUser = null;

        if (is_string($cachedValue) && $cachedValue !== '') {
            $cachedPayload = $this->decodeCachePayload($cachedValue);

            if (is_array($cachedPayload)) {
                $encryptedUser = AuthBridge::fromCacheArray($cachedPayload);
            }
        }

        if (!$encryptedUser instanceof AuthBridge) {
            $this->logger->info('Process state cache not ready yet.', [
                'processId' => $sessionId,
                'processType' => $processType,
            ]);
            $process = new ResponseDTO(false, false, false);
            return new FetchFromAccessTableResultDTO($process->toDomainStateArray(), false);
        }

        // TODO => Rename column "applications" to "credentials" => This store the list of credentials by domain or a user application credentials
        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabase($encryptedUser, "applications");
        $process = new ResponseDTO(true, true, true);

        return new FetchFromAccessTableResultDTO(
            $process->toVaultStateArray(),
            $decrypted ? $this->buildResponseFromApplications($decrypted->getApplications(), $processType) : false,
        );
    }

    private function buildResponseFromApplications(string $json, string $processType): array
    {
        try {
            $apps = $this->decodeApplications($json);

            if ($apps === null) {
                throw new \UnexpectedValueException('Invalid application data payload.');
            }

            if ($processType === 'application') {
                return array_map(fn($a) => $this->mapApplication($a, $processType), $apps);
            } else {
                return array_map(fn($a) => $this->mapDomain($a, $processType), $apps);
            }
        } catch (\Throwable $e) {
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
            'targetId' => $a->targetId,
        ];
    }

    private function mapDomain(object $a): array
    {
        return [
            'credential' => $a->userCredential,
            'description' => $a->description,
            'targetId' => $a->targetId,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeCachePayload(string $cachedValue): ?array
    {
        try {
            $cachedPayload = json_decode($cachedValue, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($cachedPayload) ? $cachedPayload : null;
    }

    /**
     * @return list<object>|null
     */
    private function decodeApplications(string $json): ?array
    {
        try {
            $decodedApplications = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decodedApplications) ? $decodedApplications : null;
    }
}
