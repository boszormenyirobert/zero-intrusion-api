<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class SharedRegistrationStateService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly SharedProcessPoller $sharedProcessPoller,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request): ?array
    {
        $processId = $this->sharedPayloadService->getProcessId($request, 'shared_registration_state');
        $this->logger->info(sprintf('Poll processId: %s', $processId));

        if (!$processId) {
            $this->logger->info(sprintf('Invalid or missing processId: %s', $processId));

            return null;
        }

        $response = $this->sharedProcessPoller->pollTheRedisDefault($processId);
        $this->logger->info(sprintf('Process state response for processId %s: %s', $processId, json_encode($response)));

        return $response;
    }
}