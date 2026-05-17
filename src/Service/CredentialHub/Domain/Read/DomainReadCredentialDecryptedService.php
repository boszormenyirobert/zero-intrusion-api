<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedPayloadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Cache\ProcessStateCacheService;

class DomainReadCredentialDecryptedService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly DomainReadService $domainReadService,
        private readonly LoggerInterface $logger,
        private readonly ProcessStateCacheService $processStateCacheService
    ) {
    }

    public function handle(Request $request): array
    {
        $user = $this->sharedPayloadService->getPayload($request, PayloadKeys::DOMAIN_READ_CREDENTIAL_ENCRYPTED);

        $result = $this->returnFromCache($user) ?  $this->returnFromCache($user) : $this->returnFromDatabase($user);                
        return $result;
    }

    private function returnFromCache(array $user): array|false
    {   

        if (array_key_exists('credentialCacheKey', $user) && array_key_exists('qrCacheKey', $user) && ($user['credentialCacheKey'] !== $user['qrCacheKey'])) {

            $response = $this->processStateCacheService->get($user['credentialCacheKey'] ?? 'missing') ?? ['credentials' => []];

            return ['credentials' => $response, 'validation' => true];
        }  
        return false;      
    }

    private function returnFromDatabase($user): array{
        $storedQrData = (array)$this->processStateCacheService->get($user['qrCacheKey'] ?? 'missing');
        $storedQrData = array_merge($storedQrData, $user);

        $decoded = (array)$storedQrData;

        $decoded['publicId'] = $user['publicId'] ?? 'missing publicId';
       
        $response = $this->domainReadService->getDecryptedCredentials($decoded);

        return ['credentials' => $response, 'domainProcessId' => $storedQrData['domainProcessId'], 'publicKey' => $storedQrData['publicKey'] ];
    }
}