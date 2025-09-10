<?php

namespace App\Controller\CredentialHub\Domain\Read;

use App\Service\AuthBridge\AuthBridgeService;
use App\Controller\PayloadValidator\PayloadValidator;
use Symfony\Component\HttpFoundation\Request;
use App\DTO\QR\DomainReadQrContentDTO;
use App\Repository\CorporateIdentityRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\AccessRegistryRepository;
use Psr\Log\LoggerInterface;
use App\Service\Notifier\NotifierService;

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

    public function getQrContent($domain, $mobilXExtensionAuth, $identity): DomainReadQrContentDTO
    {
        return new DomainReadQrContentDTO(
            $domain,
            $identity->getDomainProcessId(),
            $mobilXExtensionAuth,
            'domain-login',
            'extension',
             $identity->getIv()
        );
    }

    public function processCredentialRead(array $user): bool
    {
        if (
            !isset($user['type'], $user['source']) ||
            !in_array($user['type'], ['domain-login', 'system_hub_login'], true)
        ) {
            return false;
        }

        return match ($user['source']) {
            'corporate' => $this->handleCorporateSource($user),
            'extension' => $this->authBridgeService->persistDecryptedUserData($user),
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
