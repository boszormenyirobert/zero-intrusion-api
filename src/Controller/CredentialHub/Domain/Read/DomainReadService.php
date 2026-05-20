<?php

namespace App\Controller\CredentialHub\Domain\Read;

use App\Service\AuthBridge\AuthBridgeService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\QR\DomainReadQrContentDTO;
use App\Repository\CorporateIdentityRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\AccessRegistryRepository;
use Psr\Log\LoggerInterface;
use App\Service\Notifier\NotifierService;
use App\DTO\QR\CredentialHubIdentityDTO;

class DomainReadService
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

    public function getQrContent(string $domain, CredentialHubIdentityDTO $identity): DomainReadQrContentDTO
    {
        return new DomainReadQrContentDTO(
            $domain,
            $identity,
            'domain-login',
            'extension'
        );
    }

    public function processCredentialRead(array $user): bool
    {
        $this->logger->info('Processing credential read request.', [
            'user' => json_encode(array_keys($user)),
        ]);


        // the mobile source is extension, because the initial process is started by the extension
        return match ($user['source']) {
            'corporate' => $this->handleCorporateSource($user),
            'extension' => $this->authBridgeService->persistDecryptedUserData($user),
            default => false,
        };
    }

    public function getDecryptedCredentials(array $user): array
    {
        $this->logger->info('Attempting to retrieve decrypted credentials.', [
            'user' => $user,
        ]);
        $this->logger->info('User keys: ' . json_encode(array_keys($user)));


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
