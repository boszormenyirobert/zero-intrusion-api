<?php

declare(strict_types=1);

namespace App\Service\Device\Identity;

use App\DTO\Device\Identity\RecoverySettingsRequestDTO;
use App\Service\Identity\IdentityService;
use Psr\Log\LoggerInterface;

class RecoverySettingsService
{
    public function __construct(
        private readonly IdentityService $identityService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(RecoverySettingsRequestDTO $request): array
    {
        $this->logger->info('Incoming FCM request received.', [
            'channel' => 'fcm',
            'operation' => 'recovery_settings',
            'publicId' => $request->publicId,
            'hasFcmToken' => $request->fcmToken !== null && $request->fcmToken !== '',
            'fcmTokenPreview' => $this->maskValue($request->fcmToken),
        ]);

        $this->identityService->updateIdentityRecoverySettings($request->toArray());

        return [
            'success' => true,
        ];
    }

    private function maskValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $length = strlen($value);

            if ($length <= 10) {
                return str_repeat('*', $length);
            }

            return substr($value, 0, 6) . '...' . substr($value, -4);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->maskValue($item), $value);
        }

        return $value;
    }
}
