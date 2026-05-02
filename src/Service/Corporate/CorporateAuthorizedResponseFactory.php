<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\Service\Shared\AuthorizedEncryptedResponseFactory;

class CorporateAuthorizedResponseFactory
{
    public function __construct(
        private readonly AuthorizedEncryptedResponseFactory $authorizedEncryptedResponseFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{headers: array<string, string>, body: string}
     */
    public function create(array $payload): array
    {
        return $this->authorizedEncryptedResponseFactory->create($payload);
    }
}