<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class SharedProcessPoller
{
    public function __construct(
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function getCacheByProcessId(string $processId): array
    {
        $cachedValue = $this->processStateCacheService->get($processId);

        if (!is_string($cachedValue) || $cachedValue === '') {
            return [];
        }
        $data = json_decode($cachedValue, true);
        return $data;
    }

    public function getChacheByProcessId(string $processId): array
    {
        return $this->getCacheByProcessId($processId);
    }

    public function pollTheRedis(string $processId, object $authBridgeService, string $type): array
    {
        $startTime = microtime(true);
        $maxWait = 15;
        $response = null;
        $toAutoNotification = [];
        $list = $type === 'domain' ? 'domainList' : 'applicationList';

        do {
            $response = $authBridgeService->fetchFromAccessTable($processId, $type);
            $toAutoNotification = $this->sharedNotificationService->getUserEmailByTargetId($response);

            if (($response['process']['process_check'] ?? false) === true) {
                break;
            }

            if ((microtime(true) - $startTime) >= $maxWait) {
                break;
            }

            usleep(250000);
        } while (true);

        return array_merge(
            [$list => $response['response'] ?? []],
            $response['process'] ?? [],
            $toAutoNotification,
        );
    }

    public function pollTheRedisDefault(string $processId): array
    {
        $startTime = microtime(true);
        $maxWait = 8;
        $response = [];

        do {
            $value = $this->getCacheByProcessId($processId);
            if (is_array($value) && !empty($value)) {
                $response['cache'] = $value;
                break;
            }

            if ((microtime(true) - $startTime) >= $maxWait) {
                break;
            }

            usleep(250000);
        } while (true);

        return $response['cache'] ?? ['process_check' => false];
    }

    public function pollTheRedisOneTouch(string $processId, string $processType): array
    {
        $startTime = time();
        $maxWait = 8;
        $response = [];

        do {
            $user = $this->authBridgeService->fetchForOneTouch($processId, $processType);

            if ($user) {
                $response = $user->toOneTouchProcessArray();
                $this->logger->info('One Touch poll result', [
                    'processId' => $processId,
                    'payload' => $response,
                ]);

                break;
            }

            if ((time() - $startTime) >= $maxWait) {
                break;
            }

            usleep(500000);
        } while (true);

        return $response;
    }
}