<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendFcmNotificationMessage;
use App\Service\CredentialHub\SharedNotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendFcmNotificationMessageHandler
{
    public function __construct(
        private readonly SharedNotificationService $sharedNotificationService,
    ) {
    }

    public function __invoke(SendFcmNotificationMessage $message): void
    {
        $this->sharedNotificationService->sendFcmNotification(
            $message->source,
            $message->userPublicId,
            $message->qrContent,
            true,
        );
    }
}