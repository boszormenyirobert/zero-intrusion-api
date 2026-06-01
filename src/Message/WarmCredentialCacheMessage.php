<?php

declare(strict_types=1);

namespace App\Message;

final class WarmCredentialCacheMessage
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $domain,
        public readonly string $userPublicId,
        public readonly string $credentialCacheKey,
    ) {
    }
}
