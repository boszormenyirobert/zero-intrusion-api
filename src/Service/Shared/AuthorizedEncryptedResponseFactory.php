<?php

declare(strict_types=1);

namespace App\Service\Shared;

use App\Helper\AuthorizationHelperFactory;
use App\Service\Crypters\CrypterService;

class AuthorizedEncryptedResponseFactory
{
    public function __construct(
        private readonly CrypterService $crypterService,
        private readonly AuthorizationHelperFactory $authorizationHelperFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{headers: array<string, string>, body: string}
     */
    public function create(array $payload): array
    {
        $encryptedData = $this->crypterService->encrypt($payload);

        $authorizationHelper = $this->authorizationHelperFactory->create();

        return $authorizationHelper->buildResponse(
            $authorizationHelper->getAuthHeader($encryptedData),
            $encryptedData,
            $authorizationHelper->getIvBase64(),
        );
    }
}