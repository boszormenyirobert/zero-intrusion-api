<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use App\DTO\QR\VaultReadQrContentDTO;
use Psr\Log\LoggerInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;

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
        private readonly VaultReadCredentialDecryptedService $vaultReadCredentialDecryptedService,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function handle(ExtensionCredentialRequestDTO $request): array
    {
        // Create identity
        $identity = $this->getIdentity($request);

        // Get QR content from cache to include in notification if needed
        $qrContent = $this->processStateCacheService->get($identity->getQrCacheKey());

        $this->sharedNotificationService->sendFcmNotification('vaultRead', $request->userPublicId, $qrContent);

        $qrCode = $identity->toApplicationProcessArray();

        return $qrCode;
    }
    public function getIdentity(ExtensionCredentialRequestDTO $extensionRequest): ExtensionCredentialResponseDTO
    {
        $identity = $this->authBridgeService->generateRequestIdentity('applicationProcessId');               
        $identity->setType('vault-read');
        $identity->setPublicKey($extensionRequest->publicKey);
        $identity->setSource('extension');
        
        $qrContent = $this->vaultReadService->getQrContent($identity);
        $errors = $this->validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('vaultReadQrIdentity: ' . $error->getMessage());
            }
        }

        // Put credential data in cache if userPublicId is provided and include cache key in QR content
        $qrContent = $this->putCacheCredentialData($qrContent, $extensionRequest->userPublicId, $identity->getQrCacheKey());

        // Store QR content in cache
        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity->setQrCode(
            $this->qrService->getQrCode(
                $qrContent
            )
        );

        return $identity;
    }

    private function putCacheCredentialData(VaultReadQrContentDTO $qrContent, string $userPublicId, string $qrCacheKey): VaultReadQrContentDTO {
        if(empty($userPublicId)) {
            $this->logger->warning('User public ID is empty. Skipping credential caching.');

            return $qrContent;
        }

        $credentialCacheKey = 'credentialCacheKey_' . $qrCacheKey;
        $applicationList = $this->vaultReadCredentialDecryptedService->getApplicationCreadentials($userPublicId);
        $this->setCacheKey($credentialCacheKey, $applicationList);

        $qrContent->setCredentialCacheKey($credentialCacheKey);

        return $qrContent;
    }

    private function setCacheKey(string $key, VaultReadQrContentDTO|array $value)
    {
        $this->processStateCacheService->set($key, $value, 30);
    }    
}