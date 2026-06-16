<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry;

use App\Entity\AccessRegistry;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\Cache\ProcessStateCacheService;
use JsonException;
use Psr\Log\LoggerInterface;

final class AccessRegistryDomainService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AuthBridgeService $authBridgeService,
        private readonly ResolverService $resolverService,
        private readonly ProcessStateCacheService $processStateCacheService
    ) {}

    public function isAllowedUserDomainApplicationCombination(array $user, string $type): array
    {
        $result = [
            "newCombination" => true,
            "existingPage" => ""
        ];

        if( ( $user['type']  === 'registration-domain' || $type  === 'registration-application')&& $user['update'] === false) {
            return $result;
        }
        
        $encryptedUserPages = $this->resolverService->getFilter()->getUserRegistratedPages($user, $type);
        $decryptedUserPages = [];
        if (!empty($encryptedUserPages)) {
            $decryptedUserPages = $this->resolverService->getDecrypt()->getUserDecryptedPages($encryptedUserPages, $type);
        }

        if (!empty($decryptedUserPages)) {
            $result = $this->resolverService->getCheck()->userDomainCombinationExists($user, $decryptedUserPages, $type);
        }
        return $result;
    }

    public function createDomain(array $userData, string $type): array
    {
        $sessionId = $userData['sessionId'] ?? $userData['registrationProcessId'] ?? null;
        
        if( $userData['registrationProcessId'] ?? false){
            $processId = $userData['registrationProcessId'];
            $this->authBridgeService->updateProcessState('registrationProcessId', $processId);
        }        
        $encryptedUserData = $this->resolverService->getWrite()->createAccessRegistryDomain($userData, $type);

        $userData['encryptedAuthId'] = $encryptedUserData->getUserCredential();
        $userData['state'] = true;

        $this->writeLoginEntryInRedis($sessionId, [
            'process' => true,
            'validation' => true,
            'process_check' => true,
            'success' => true,
        ]);
        
        return $userData;
    }    

    public function deleteDomainRegistraions(array $user, string $type = 'domain'): void
    {
        $encryptedUserPages =  $this->resolverService->getFilter()->getUserRegistratedPages($user, $type);
        $collection = [];
        if (!empty($encryptedUserPages)) {
            $collection = $this->resolverService->getDecrypt()->getUserEncryptedDecryptedPageCollection($encryptedUserPages);
        }
        if (!empty($collection)) {
            $this->resolverService->getDelete()->deleteUserDomainCombination($user, $collection);
        }
    }

    /** use case not found */
    public function getDecryptedUser(array $user, string $type): ?AccessRegistry
    {
        $decryptedUserPages = [];
        $decryptedPage = null;

        $encryptedUserPages = $this->resolverService->getFilter()->getUserRegistratedPages($user, $type);

        if (!empty($encryptedUserPages)) {
            $decryptedUserPages =  $this->resolverService->getDecrypt()->getUserDecryptedPages($encryptedUserPages, $type);
        }

        if (!empty($encryptedUserPages) && !empty($decryptedUserPages)) {
            $decryptedPage = $this->resolverService->getCheck()->getUserDomainCombination($user, $decryptedUserPages);
        }

        return $decryptedPage;
    }

    private function writeLoginEntryInRedis(string $processId, array $status): void
    {
        try {
            $this->processStateCacheService->set(
                $processId,
                $this->encodeJson($status, JSON_UNESCAPED_UNICODE),
                300
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Process state cache write failed.', [
                'processId' => $processId,
                'status' => $status,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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
