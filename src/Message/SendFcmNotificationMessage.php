<?php

declare(strict_types=1);

namespace App\Message;

final class SendFcmNotificationMessage
{
    public function __construct(
        public readonly string $source,
        public readonly string $userPublicId,
        public readonly mixed $qrContent,
    ) {
    }
}