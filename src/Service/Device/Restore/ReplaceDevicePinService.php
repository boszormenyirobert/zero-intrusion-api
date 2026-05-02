<?php

declare(strict_types=1);

namespace App\Service\Device\Restore;

use App\DTO\Device\Restore\ReplaceDevicePinRequestDTO;
use App\Service\Restore\RestoreService;

class ReplaceDevicePinService
{
    public function __construct(
        private readonly RestoreService $restoreService,
    ) {
    }

    public function handle(ReplaceDevicePinRequestDTO $request): array
    {
        return $this->restoreService->replaceValidation($request->toArray());
    }
}
