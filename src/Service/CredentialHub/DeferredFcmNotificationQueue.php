<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Message\SendFcmNotificationMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class DeferredFcmNotificationQueue
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enqueue(string $source, ?string $userPublicId, mixed $qrContent): void
    {
        if ($userPublicId === null || $userPublicId === '') {
            return;
        }

        try {
            $this->messageBus->dispatch(new SendFcmNotificationMessage($source, $userPublicId, $qrContent));
        } catch (\Throwable $exception) {
            $this->logger->error('FCM notification dispatch failed; request flow continues.', [
                'source' => $source,
                'userPublicId' => $userPublicId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}