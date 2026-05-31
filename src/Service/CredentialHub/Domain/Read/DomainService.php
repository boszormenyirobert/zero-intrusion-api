<?php

namespace App\Service\CredentialHub\Domain\Read;

use App\Service\AuthBridge\AuthBridgeService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Repository\CorporateIdentityRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\AccessRegistryRepository;
use Psr\Log\LoggerInterface;
use App\Service\Notifier\NotifierService;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use App\DTO\CredentialHub\QrContentDTO;

class DomainService
{
    public function __construct(
        private PayloadValidator $payloadValidator,
        private AuthBridgeService $authBridgeService,
        private CorporateIdentityRepository $corporateIdentityRepository,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private AccessRegistryRepository $accessRegistryRepository,
        private NotifierService $notifierService
    ) {}

    public function getQrContent( ExtensionCredentialResponseDTO $identity): QrContentDTO
    {
        return new QrContentDTO(
            $identity
        );
    }

    public function setByUserSignedCredentialsInCache(array $user): bool
    {
        // the mobile source is extension, because the initial process is started by the extension
        return match ($user['source']) {
            'corporate' => $this->handleCorporateSource($user),
            'extension' => $this->authBridgeService->persistDecryptedUserData($user),
            default => false,
        };
    }

    public function getDecryptedCredentials(array $user): array
    {
        // the mobile source is extension, because the initial process is started by the extension
        return match ($user['source']) {
            'corporate' => $decryptedResponse = $this->authBridgeService->persistDecryptedUserDataForWeb($user),
            'extension' => $this->authBridgeService->getDecryptedUserDataToMobileRequest($user),
            default => false,
        };
    }

    private function handleCorporateSource(array $user): bool
    {
        $decryptedResponse = $this->authBridgeService->persistDecryptedUserDataForWeb($user);
        if (!$decryptedResponse) {
            return false;
        }

        $this->notifierService->callBackUserLogin($decryptedResponse, $user);
        return true;
    }
}
