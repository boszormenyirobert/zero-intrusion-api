<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Service\CredentialHub\Shared\ReadCredentialDecryptedStrategyInterface;

class DomainReadCredentialDecryptedStrategy implements ReadCredentialDecryptedStrategyInterface
{
    public function __construct(
        private readonly DomainService $domainService,
    ) {
    }

    public function resolve(array $context): array
    {
        $decoded = $context;
        $decoded['publicId'] = $context['publicId'] ?? 'missing publicId';

        $response = $this->domainService->getDecryptedCredentials($decoded);

        return [
            'credentials' => $response,
            'sessionId' => $context['sessionId'] ?? ($context['domainProcessId'] ?? 'missing'),
            'publicKey' => $context['publicKey'] ?? 'missing',
        ];
    }
}
