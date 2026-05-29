<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use Psr\Log\LoggerInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\DTO\QR\StoreDTO;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\DTO\CredentialHub\QrContentDTO;
use App\Service\CredentialHub\SharedNotificationService;

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
        private readonly ValidatorInterface $validator,
        private readonly Encryptor $encryptor,
        private readonly SharedNotificationService $sharedNotificationService
    ) {
    }

    public function getIdentity(ExtensionCredentialRequestDTO $extensionRequest, string $type): ExtensionCredentialResponseDTO
    {
        $identity = $this->identityConfiguration($extensionRequest, $type, 'extension');
        
        $qrContent = $this->qrService->getQrContent($identity);
        $errors = $this->validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('vaultReadQrIdentity: ' . $error->getMessage());
            }
        }

        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity = $this->setQrCode($identity); 

        return $identity;
    }

    public function handleNotification(
        ExtensionCredentialRequestDTO $identityRequestDTO, 
        ExtensionCredentialResponseDTO $identity, 
        QrContentDTO $qrContent): void
        {
        if ($identityRequestDTO->userPublicId !== null && $identityRequestDTO->userPublicId !== '') {

            $credentialCacheKey =  $this->storeCredentialDataInCache($identityRequestDTO, $identity, $identity->getType());

            $qrContent->setCredentialCacheKey($credentialCacheKey);

            if($identity->getType() === 'domain-read') {
                $santizedQrContent = $qrContent->toNotificationDomain();
            } else if($identity->getType() === 'vault-read') {
                $santizedQrContent = $qrContent->toNotificationApplication();
            } else {
                $santizedQrContent = [];
            }

            $this->sharedNotificationService->sendFcmNotification('domainRead', $identityRequestDTO->userPublicId, $santizedQrContent);
        }        
    }

    private function identityConfiguration(
        ExtensionCredentialRequestDTO $extensionRequest, 
        string $type, 
        string $source): ExtensionCredentialResponseDTO
    {
        $identity = $this->authBridgeService->generateRequestIdentity($type);               
        $identity->setType($type);
        $identity->setPublicKey($extensionRequest->publicKey);
        $identity->setSource($source);

        if($type === 'domain-read'){
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

    private function storeCredentialDataInCache( 
        ExtensionCredentialRequestDTO $identityRequestDTO,  
        ExtensionCredentialResponseDTO $identity, 
        string $type): string
        {
            $credentialCacheKey = 'credentialCacheKey_' . $identity->getQrCacheKey();

            $credentials = $this->getCredentials($identityRequestDTO, $type);
            
            $this->setCacheKey($credentialCacheKey, $credentials);
            
            return $credentialCacheKey;
    }

    private function getCredentials(ExtensionCredentialRequestDTO $identityRequestDTO, string $type): array{
            if($type === 'domain-read') {
                return $this->getDomainCredentials($identityRequestDTO);
            }

            if($type === 'vault-read') {
                 return $this->getApplicationCredentials($identityRequestDTO);
            }
            return [];
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