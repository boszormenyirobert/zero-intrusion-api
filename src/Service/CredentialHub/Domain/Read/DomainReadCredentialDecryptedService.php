<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedPayloadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class DomainReadCredentialDecryptedService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly DomainReadService $domainReadService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request): array
    {
        $user = $this->sharedPayloadService->getPayload($request, PayloadKeys::DOMAIN_READ_CREDENTIAL_ENCRYPTED);
        $processId = $user['domainProcessId'] ?? 'missing';

        $this->logger->info(sprintf('domainReadCredentialDecrypted started for processId: %s', $processId));
        $response = $this->domainReadService->getDecryptedCredentials($user);
        $this->logger->info(sprintf('domainReadCredentialDecrypted finished for processId: %s', $processId));

        return ['credentials' => $response];
    }
}