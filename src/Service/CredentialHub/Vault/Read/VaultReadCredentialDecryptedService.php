<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Cache\ProcessStateCacheService;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Psr\Log\LoggerInterface;

class VaultReadCredentialDecryptedService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly VaultReadService $vaultReadService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request): array
    {
        $user = $this->sharedPayloadService->getPayload($request, PayloadKeys::VAULT_READ_CREDENTIAL_ENCRYPTED);
        $result = $this->returnFromCache($user) ?  $this->returnFromCache($user) : $this->returnFromDatabase($user);  

        return $result;
    }

    private function returnFromCache(array $user): array|false
    {   

        if (array_key_exists('credentialCacheKey', $user) && array_key_exists('qrCacheKey', $user) && ($user['credentialCacheKey'] !== $user['qrCacheKey'])) {
            $response = $this->processStateCacheService->get($user['credentialCacheKey'] ?? 'missing') ?? ['credentials' => []];
            return ['credentials' => $response, 'validation' => true];
        }  
        return false;      
    }


    public function returnFromDatabase($user): array {
        
        $rawQrData = $this->processStateCacheService->get($user['qrCacheKey']);

        $storedQrData = $this->objectToArrayRecursive($rawQrData);

        $storedQrData = array_merge($storedQrData, (array)$user);
        $decoded = [];
        $decoded['publicId'] = $user['publicId'] ?? null;

        $applicationList = $this->getApplicationCreadentials($decoded['publicId'] ?? '');

        return ['credentials' => $applicationList,'publicKey' => $storedQrData['publicKey'] ?? 'missing', 'validation' => true,'processId' => $storedQrData['applicationProcessId'] ?? 'missing' ];
    }

    public function getApplicationCreadentials(string $userPublicId): array
    {
        if(empty($userPublicId)) {
            $this->logger->warning('User public ID is empty. Skipping credential retrieval.');
            return [];
        }

        $applicationList = [];
        $getPages = $this->accessRegistryRepository->findBy(['publicId' => $userPublicId]);
        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $decrypted = $this->crypterDatabaseAccessRegistryService->decryptFromDatabaseOrFail($userPage, "application");

                $applicationList[] = [
                    'application' => $decrypted->getApplication(),
                    'credential' => $decrypted->getUserCredential(), 
                    'description' => $decrypted->getDescription(),
                    'targetId' => $decrypted->getTargetId()                                      
                ];
            }
        }

        return $applicationList;
    }    

    private function objectToArrayRecursive($data)
    {
        if (is_object($data)) {
            $data = (array)$data;
        }

        if (is_array($data)) {
            return array_map([$this, 'objectToArrayRecursive'], $data);
        }

        return $data;
    }
}