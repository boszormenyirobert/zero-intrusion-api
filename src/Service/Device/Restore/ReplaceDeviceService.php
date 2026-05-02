<?php

declare(strict_types=1);

namespace App\Service\Device\Restore;

use App\DTO\Device\Restore\ReplaceDeviceRequestDTO;
use App\Service\Identity\IdentityService;
use App\Service\Restore\RestoreService;

class ReplaceDeviceService
{
    public function __construct(
        private readonly IdentityService $identityService,
        private readonly RestoreService $restoreService,
    ) {
    }

    public function handle(ReplaceDeviceRequestDTO $request): array
    {
        $notifications = [
            'success' => false,
            'deviceHash' => 'missing',
            'message' => 'Something went wrong. Please try again later',
        ];

        $secret = $this->identityService->getSecret($request->toArray());

        if (!empty($secret)) {
            $notifications = $this->restoreService->recoveryNotification($secret);
        }

        return $notifications;
    }
}
