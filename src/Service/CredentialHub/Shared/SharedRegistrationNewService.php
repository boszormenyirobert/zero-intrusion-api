<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewResultDTO;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\Payload\JsonPayloadDecoder;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class SharedRegistrationNewService
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly AccessRegistryRegistrationService $accessRegistryRegistrationService,
        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(Request $request): SharedRegistrationNewResultDTO
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new');
        $user = $this->jsonPayloadDecoder->requireArray(
            $validatedPayload['shared_registration_new'] ?? null,
            'Invalid shared registration new payload.'
        );

        if ($this->isEncryptedNewCredentialPayload($user)) {
            $sessionId = $this->requireNonEmptyString($user, 'sessionId');
            $userPublicId = $this->resolveUserPublicId($user, $sessionId);

            $this->requireNonEmptyString($user, 'encryptedData');

            $fcmPayload = [
                'userPublicId' => $userPublicId,
                'encryptedAesKey' => $this->requireNonEmptyString($user, 'encryptedAesKey'),
                'encryptedData' => $this->requireNonEmptyString($user, 'encryptedData'),
                'iv' => $this->requireNonEmptyString($user, 'iv'),
                'sessionId' => $sessionId,
                'type' => 'new-user-credential-silent',
                'targetId' => $user['targetId'] ?? null,
            ];

            $this->sharedNotificationService->sendFcmNotification('newUserCredential', $userPublicId, $fcmPayload, true);

            return new SharedRegistrationNewResultDTO(['forwarded' => true], '');
        }

        return new SharedRegistrationNewResultDTO($registeredUser, '');
    }

    public function handleSave(Request $request): SharedRegistrationNewResultDTO
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new_save');
        $user = $this->jsonPayloadDecoder->requireArray(
            $validatedPayload['shared_registration_new_save'] ?? null,
            'Invalid shared registration new save payload.'
        );

        // registration-domain
        // registration-application
        // system_hub_registration
        // update-applications

        $type = $user['type'];
        $key = in_array($type, ['registration-domain', 'system_hub_registration'], true) ? 'domain' : 'application';
        
        $registeredUser = $this->accessRegistryRegistrationService->addAccessRegistry($user, $key, $type === 'system_hub_registration');

        if ($type === 'system_hub_registration') {
            $this->accessRegistryRegistrationService->sendNotification($registeredUser, $user);
        }

        return new SharedRegistrationNewResultDTO($registeredUser, '');
    }

    private function isEncryptedNewCredentialPayload(array $payload): bool
    {
        return isset($payload['encryptedAesKey'], $payload['encryptedData'], $payload['sessionId']);
    }

    private function resolveUserPublicId(array $payload, string $sessionId): string
    {
        $cached = $this->processStateCacheService->get(sprintf('%s_userPublicId', $sessionId));
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (isset($payload['userPublicId']) && is_string($payload['userPublicId']) && $payload['userPublicId'] !== '') {
            return $payload['userPublicId'];
        }

        throw new \InvalidArgumentException('Invalid or missing userPublicId for encrypted shared registration notification.');
    }

    private function requireNonEmptyString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException(sprintf('Invalid or missing %s.', $key));
        }

        return $value;
    }
}