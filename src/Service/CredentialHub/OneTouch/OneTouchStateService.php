<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\OneTouch;

use App\Controller\CredentialHub\PayloadKeys;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use Symfony\Component\HttpFoundation\Request;

class OneTouchStateService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly SharedProcessPoller $sharedProcessPoller,
    ) {
    }

    public function handle(Request $request): array
    {
        $processId = $this->sharedPayloadService->getProcessId($request, PayloadKeys::ONE_TOUCH_STATE, false);

        if (!$processId) {
            throw new \InvalidArgumentException('Invalid or missing processId');
        }

        return $this->sharedProcessPoller->pollTheRedisOneTouch($processId, 'oneTouchProcessId');
    }
}
