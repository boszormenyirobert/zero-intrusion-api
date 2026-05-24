<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\Cache\ProcessStateCacheService;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\DTO\QR\StoreDTO;
use App\DTO\CredentialHub\QrContentDTO;

class DomainReadQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly DomainReadService $domainReadService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly LoggerInterface $logger,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseUserService,
        private readonly Encryptor $encryptor,
        private readonly ValidatorInterface $validator
    ) {
    }

    public function handle(ExtensionCredentialRequestDTO $request): array
    {
        // Create identity
        $identity = $this->getIdentity($request);         
        // Get QR content from cache to include in notification if needed
        $qrCacheKey = $identity->getQrCacheKey();
        $qrContent = $this->processStateCacheService->get($qrCacheKey);        
                 
        // Auto notify if userPublicId is provided
        $this->handleNotification($request, $identity, $qrContent);      

        $qrCode = $identity->toProcessArray($identity->getType());

        return $qrCode;
    }

    private function getIdentity(ExtensionCredentialRequestDTO $extensionRequest): ExtensionCredentialResponseDTO
    {
        $identity = $this->authBridgeService->generateRequestIdentity('domainProcessId');
        $identity->setType('domain-read');
        $identity->setPublicKey($extensionRequest->publicKey);
        $identity->setSource('extension');
        $identity->setDomain($extensionRequest->domain);

        $qrContent = $this->domainReadService->getQrContent($identity);
        $errors = $this->validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('domainReadQrIdentity: ' . $error->getMessage());
            }
        }
        // Store QR content in cache for later retrieval in notification
        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity->setQrCode(
            $this->qrService->getQrCode([
                'qrCacheKey' => $identity->getQrCacheKey(),
                'type' => 'domain-login'
            ]
        )); 

        return $identity;
    }

    private function handleNotification(
        ExtensionCredentialRequestDTO $identityRequestDTO, 
        ExtensionCredentialResponseDTO $identity, 
        QrContentDTO $qrContent): void
        {
        if ($identityRequestDTO->userPublicId !== null && $identityRequestDTO->userPublicId !== '') {

            $credentialCacheKey = $this->putCacheCredentialData($identityRequestDTO, $identity);
           
            $qrContent->setCredentialCacheKey($credentialCacheKey);

            $santizedQrContent = $qrContent->toNotification();

            $this->sharedNotificationService->sendFcmNotification('domainRead', $identityRequestDTO->userPublicId, $santizedQrContent);
        }        
    }

    private function putCacheCredentialData( 
        ExtensionCredentialRequestDTO $identityRequestDTO,  
        ExtensionCredentialResponseDTO $identity): string
        {
            $storeDTO = new StoreDTO($identityRequestDTO->domain, $identityRequestDTO->userPublicId);
            $credentialCacheKey = 'credentialCacheKey_' . $identity->getQrCacheKey();
            $credentials = $this->encryptor->preapredCredentials($storeDTO);

            $this->setCacheKey($credentialCacheKey, $credentials);

            return $credentialCacheKey;
    }

    private function setCacheKey(string $key, QrContentDTO|array $value)
    {
        $this->processStateCacheService->set($key, $value, 30);
    }
}