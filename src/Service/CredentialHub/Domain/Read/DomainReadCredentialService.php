<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Service\CredentialHub\Domain\Read\DomainService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedPayloadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class DomainReadCredentialService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly DomainService $domainService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request): bool
    {
        $user = $this->sharedPayloadService->getPayload($request, PayloadKeys::DOMAIN_READ_CREDENTIAL);
        $response = $this->domainService->setByUserSignedCredentialsInCache($user);

        return $response;
    }
}