<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\WarmCredentialCacheMessage;
use App\Service\CredentialHub\CredentialReadService;
use App\Service\CredentialHub\IdentityType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WarmCredentialCacheMessageHandler
{
    public function __construct(
        private readonly CredentialReadService $credentialReadService,
    ) {
    }

    public function __invoke(WarmCredentialCacheMessage $message): void
    {
        $this->credentialReadService->warmCredentialCache(
            IdentityType::from($message->type),
            $message->domain,
            $message->userPublicId,
            $message->credentialCacheKey,
        );
    }
}
