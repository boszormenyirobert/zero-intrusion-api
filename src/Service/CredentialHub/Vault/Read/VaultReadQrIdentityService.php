<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use App\DTO\QR\VaultReadQrContentDTO;
use Psr\Log\LoggerInterface;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;

class VaultReadQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly VaultReadService $vaultReadService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly LoggerInterface $logger,
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,

    ) {
    }

    public function handle(VaultReadQrIdentityRequestDTO $request): array
    {
        $identity = $this->getIdentity($request);
        $qrContent = $this->processStateCacheService->get($identity->getQrCacheKey());

        $this->sharedNotificationService->sendFcmNotification('vaultRead', $request->userPublicId, $qrContent);

        $qrCode = $identity->toApplicationProcessArray();
        $this->logger->info('Vault read QR identity generated and notification sent.', [
            'type' => $qrCode['type'],
        ]);

        return $qrCode;
    }

    public function getIdentity(VaultReadQrIdentityRequestDTO $request): CredentialHubIdentityDTO
    {
        $identity = $this->authBridgeService->generateRequestIdentity('applicationProcessId');               
        $identity->setType('vault-read');

        $identity->setPublicKey($request->publicKey);
        $this->logger->info('Generated vault read QR identity.', [
            'applicationProcessId' => \json_encode($request),
        ]);

        $qrContent = $this->vaultReadService->getQrContent($request, $identity);

        // Store QR content in cache for later retrieval in notification
        

        $credentialCacheKey = 'credentialCacheKey_' . $identity->getQrCacheKey();
        $qrContent->setCredentialCacheKey($credentialCacheKey);

        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        // TODO
        // Set credentialCacheKey value in redis => Get credentials from DB

        $applicationList = [];
        $getPages = $this->accessRegistryRepository->findBy(['publicId' => $request->userPublicId]);
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
        // Put in redis: $applicationList
        $this->setCacheKey($credentialCacheKey, $applicationList);
        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        return $identity;
    }

    private function setCacheKey(string $key, VaultReadQrContentDTO|array $value)
    {
        $this->processStateCacheService->set($key, $value, 30);
    }    
}