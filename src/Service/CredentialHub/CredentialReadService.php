<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Service\Cache\ProcessStateCacheService;
use Psr\Log\LoggerInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\DTO\QR\StoreDTO;
use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\DTO\QR\VaultReadQrContentDTO;
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
        private readonly VaultReadService $vaultReadService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly LoggerInterface $logger,
        private readonly VaultReadCredentialDecryptedService $vaultReadCredentialDecryptedService,
        private readonly ValidatorInterface $validator,
        private readonly DomainReadService $domainReadService,
        private readonly Encryptor $encryptor,
        private readonly SharedNotificationService $sharedNotificationService
    ) {
    }

    private function getIdentity(
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

    public function getVaultIdentity(ExtensionCredentialRequestDTO $extensionRequest): ExtensionCredentialResponseDTO
    {
        $identity = $this->getIdentity($extensionRequest, 'vault-read','extension');
        
        $qrContent = $this->vaultReadService->getQrContent($identity);
        $errors = $this->validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('vaultReadQrIdentity: ' . $error->getMessage());
            }
        }

        // Put credential data in cache if userPublicId is provided and include cache key in QR content
        $qrContent = $this->putCacheCredentialDataVault($qrContent, $extensionRequest->userPublicId, $identity->getQrCacheKey());

        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity = $this->setQrCode($identity); 

        return $identity;
    }

    public function getDomainIdentity(ExtensionCredentialRequestDTO $extensionRequest): ExtensionCredentialResponseDTO
    {
        $identity = $this->getIdentity($extensionRequest, 'domain-read','extension');

        $qrContent = $this->domainReadService->getQrContent($identity);
        $errors = $this->validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('domainReadQrIdentity: ' . $error->getMessage());
            }
        }

        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity = $this->setQrCode($identity); 

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

    public function putCacheCredentialData( 
        ExtensionCredentialRequestDTO $identityRequestDTO,  
        ExtensionCredentialResponseDTO $identity): string
        {
            $storeDTO = new StoreDTO($identityRequestDTO->domain, $identityRequestDTO->userPublicId);
            $credentialCacheKey = 'credentialCacheKey_' . $identity->getQrCacheKey();
            $credentials = $this->encryptor->preapredCredentials($storeDTO);

            $this->setCacheKey($credentialCacheKey, $credentials);

            return $credentialCacheKey;
    }


    public function putCacheCredentialDataVault(VaultReadQrContentDTO $qrContent, string $userPublicId, string $qrCacheKey): VaultReadQrContentDTO {
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

    private function setCacheKey(string $key,  $value)
    {
        $this->processStateCacheService->set($key, $value, 30);
    }
    
    public function handleNotification(
        ExtensionCredentialRequestDTO $identityRequestDTO, 
        ExtensionCredentialResponseDTO $identity, 
        QrContentDTO $qrContent): void
        {
        if ($identityRequestDTO->userPublicId !== null && $identityRequestDTO->userPublicId !== '') {

            $credentialCacheKey =  $this->putCacheCredentialData($identityRequestDTO, $identity);
           
            $qrContent->setCredentialCacheKey($credentialCacheKey);

            $santizedQrContent = $qrContent->toNotification();

            $this->sharedNotificationService->sendFcmNotification('domainRead', $identityRequestDTO->userPublicId, $santizedQrContent);
        }        
    }    
}