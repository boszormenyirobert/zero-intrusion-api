<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

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
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly LoggerInterface $logger,
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private readonly VaultReadCredentialDecryptedService $vaultReadCredentialDecryptedService,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function getIdentity(
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
}