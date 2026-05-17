<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\DTO\CredentialHub\Domain\Read\DomainReadQrIdentityRequestDTO;
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
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\DomainReadQrContentDTO;

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
        private readonly Encryptor $encryptor

    ) {
    }

    public function handle(DomainReadQrIdentityRequestDTO $request, ValidatorInterface $validator): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('domainProcessId');
        $identity->setPublicKey($request->publicKey);

        $qrContent = $this->domainReadService->getQrContent($request->domain, $identity);
        $errors = $validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('domainReadQrIdentity: ' . $error->getMessage());
            }
        }

        $this->setCacheKey($identity->getQrCacheKey(), $qrContent);

        $identity->setQrCode($this->qrService->getQrCode(['qrCacheKey' => $identity->getQrCacheKey(),'type' => 'domain-login']));                           

        // Auto notify if userPublicId is provided
        $this->handleNotification($request, $identity, $qrContent);      

        return $identity->toProcessArray('domainProcessId');
    }

    public function handleNotification(
        DomainReadQrIdentityRequestDTO $identityRequestDTO, 
        CredentialHubIdentityDTO $identity, 
        DomainReadQrContentDTO $qrContent): void
        {
        if ($identityRequestDTO->userPublicId !== null && $identityRequestDTO->userPublicId !== '') {



            $storeDTO = new StoreDTO($identityRequestDTO->domain, $identityRequestDTO->userPublicId);
            $credentialCacheKey = 'credentialCacheKey_' . $identity->getQrCacheKey();
            $credentials = $this->encryptor->preapredCredentials($storeDTO);

            $this->setCacheKey($credentialCacheKey, $credentials);
           
            $qrContent->setCredentialCacheKey($credentialCacheKey);

            $santizedQrContent = $qrContent->toNotification();

            $this->sharedNotificationService->sendFcmNotification('domainRead', $identityRequestDTO->userPublicId, $qrContent);
        }        
    }

    private function setCacheKey(string $key, DomainReadQrContentDTO|array $value)
    {
        $this->processStateCacheService->set($key, $value, 30);
    }
}