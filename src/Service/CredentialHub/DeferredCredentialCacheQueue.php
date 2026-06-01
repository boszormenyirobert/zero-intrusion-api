<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Message\WarmCredentialCacheMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DeferredCredentialCacheQueue
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enqueue(IdentityType $type, ?string $domain, ?string $userPublicId, string $credentialCacheKey): void
    {
        if ($userPublicId === null || $userPublicId === '') {
            return;
        }

        try {
            $this->messageBus->dispatch(new WarmCredentialCacheMessage(
                $type->value,
                $domain,
                $userPublicId,
                $credentialCacheKey,
            ));
        } catch (\Throwable $exception) {
            $this->logger->error('Credential cache dispatch failed; request flow continues.', [
                'type' => $type->value,
                'userPublicId' => $userPublicId,
                'credentialCacheKey' => $credentialCacheKey,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
