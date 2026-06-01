<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use Psr\Log\LoggerInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\DTO\QR\StoreDTO;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\DTO\CredentialHub\QrContentDTO;
use App\Service\CredentialHub\Shared\QrContentValidationService;
use App\Service\CredentialHub\DeferredFcmNotificationQueue;

enum IdentityType: string
{
    case DOMAIN_READ = 'domain-read';
    case VAULT_READ = 'vault-read';
}

class CredentialReadService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly LoggerInterface $logger,
        private readonly VaultReadCredentialDecryptedService $vaultReadCredentialDecryptedService,
        private readonly QrContentValidationService $qrContentValidationService,
        private readonly Encryptor $encryptor,
        private readonly DeferredCredentialCacheQueue $deferredCredentialCacheQueue,
        private readonly DeferredFcmNotificationQueue $deferredFcmNotificationQueue
    ) {
    }

    public function getIdentity(ExtensionCredentialRequestDTO $extensionRequest, IdentityType $type): ExtensionCredentialResponseDTO
    {
        $identity = $this->identityConfiguration($extensionRequest, $type, 'extension');
        
        $qrContent = $this->qrService->getQrContent($identity);
        $this->qrContentValidationService->validateOrFail($qrContent, $type->value);

        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity = $this->setQrCode($identity); 

        return $identity;
    }

    public function handleNotification(
        ExtensionCredentialRequestDTO $identityRequestDTO, 
        ExtensionCredentialResponseDTO $identity, 
        IdentityType $type,
        QrContentDTO $qrContent): void
        {
        if ($identityRequestDTO->userPublicId !== null && $identityRequestDTO->userPublicId !== '') {

            $credentialCacheKey = $this->createCredentialCacheKey($identity);

            $this->deferredCredentialCacheQueue->enqueue(
                $type,
                $identityRequestDTO->domain,
                $identityRequestDTO->userPublicId,
                $credentialCacheKey,
            );

            $qrContent->setCredentialCacheKey($credentialCacheKey);
            
            $santizedQrContent = $this->sanitizeQrContent($qrContent, $type);

            $source = match ($type) {
                IdentityType::VAULT_READ => 'vaultRead',
                IdentityType::DOMAIN_READ => 'domainRead',
            };

            $this->deferredFcmNotificationQueue->enqueue($source, $identityRequestDTO->userPublicId, $santizedQrContent);
        }        
    }

    private function sanitizeQrContent(QrContentDTO $qrContent, IdentityType $type): array
    {
        return match ($type) {
            IdentityType::DOMAIN_READ => $qrContent->toNotificationDomain(),
            IdentityType::VAULT_READ => $qrContent->toNotificationApplication(),
        };
    }

    private function identityConfiguration(
        ExtensionCredentialRequestDTO $extensionRequest, 
        IdentityType $type,
        string $source): ExtensionCredentialResponseDTO
    {
        $identity = $this->authBridgeService->generateRequestIdentity($type->value);
        $identity->setType($type->value);
        $identity->setPublicKey($extensionRequest->publicKey);
        $identity->setSource($source);

        if ($type === IdentityType::DOMAIN_READ) {
            $identity->setDomain($extensionRequest->domain);
        }

        return $identity;
    }

    private function setQrCode(ExtensionCredentialResponseDTO $identity): ExtensionCredentialResponseDTO
    {
        $identity->setQrCode(
            $this->qrService->getQrCode([
                'qrCacheKey' => $identity->getQrCacheKey(),
                'type' => $identity->getType()
            ]
        )); 

        return $identity;
    }

    public function warmCredentialCache(IdentityType $type, ?string $domain, string $userPublicId, string $credentialCacheKey): void
    {
        $request = new ExtensionCredentialRequestDTO($domain, $userPublicId, null);
        $credentials = $this->getCredentials($request, $type);
        $this->setCacheKey($credentialCacheKey, $credentials);
    }

    private function createCredentialCacheKey(ExtensionCredentialResponseDTO $identity): string
    {
        return 'credentialCacheKey_' . $identity->getQrCacheKey();
    }

    private function getCredentials(ExtensionCredentialRequestDTO $identityRequestDTO, IdentityType $type): array
    {
        return match ($type) {
            IdentityType::DOMAIN_READ => $this->getDomainCredentials($identityRequestDTO),
            IdentityType::VAULT_READ => $this->getApplicationCredentials($identityRequestDTO),
        };
    }

    private function getDomainCredentials(ExtensionCredentialRequestDTO $identityRequestDTO){
        $storeDTO = new StoreDTO($identityRequestDTO->domain, $identityRequestDTO->userPublicId);
        return $this->encryptor->preapredCredentials($storeDTO);
    }

    private function getApplicationCredentials(ExtensionCredentialRequestDTO $identityRequestDTO){
        return $this->vaultReadCredentialDecryptedService->getApplicationCreadentials($identityRequestDTO->userPublicId);
    }

    private function setCacheKey(string $key,  $value)
    {
        $this->processStateCacheService->set($key, $value, 30);
    }    
}