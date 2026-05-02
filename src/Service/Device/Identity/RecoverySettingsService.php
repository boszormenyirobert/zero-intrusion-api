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
        $this->logger->info('Processing recovery settings update.', [
            'publicId' => $request->publicId,
        ]);

        $this->identityService->updateIdentityRecoverySettings($request->toArray());

        return [
            'success' => true,
        ];
    }
}
