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



    private function validatePayload(Request $request){
        $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new');

        $user = $this->jsonPayloadDecoder->requireArray(
            $validatedPayload['shared_registration_new'] ?? null,
            'Invalid shared registration new payload.'
        );
        return $user;
    }

    public function handleCredentialRegistration(Request $request): SharedRegistrationNewResultDTO
    {
        $user = $this->validatePayload($request);
        $registeredUser = [];
        $sessionId = $this->requireNonEmptyString($user, 'sessionId');

        $this->processStateCacheService->set($sessionId, [
            'success' => true,
        ], 160);   

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

            return new SharedRegistrationNewResultDTO(['state' => 'in progress'], '');
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

        $key = $this->mapTypeToKey($user);
        $registeredUser = $this->accessRegistryRegistrationService->addAccessRegistry($user, $key);

        $this->accessRegistryRegistrationService->callBackUserRegistration($registeredUser, $user);

        return new SharedRegistrationNewResultDTO($registeredUser, '');
    }

    private function mapTypeToKey($user): string {
        if(array_key_exists('type', $user) && is_string($user['type'])){
            switch ($user['type']) {
                case 'registration-domain':
                    return 'domain';
                case 'registration-application':
                    return 'application';
                case 'update-applications':
                    return 'application';
                case 'new-user-credential':
                    return 'system_hub_registration';   
                default:
                    throw new \InvalidArgumentException('Invalid type in shared registration new save payload.');
            }
        }
        throw new \InvalidArgumentException('Missing type in shared registration new save payload.');
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

        if (isset($payload['publicId']) && is_string($payload['publicId']) && $payload['publicId'] !== '') {
            return $payload['publicId'];
        }
        throw new \InvalidArgumentException('Invalid or missing userPublicId for encrypted shared registration notification.');
    }

    private function requireNonEmptyString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
         throw new \InvalidArgumentException(sprintf('Invalid or missing %s from SharedRegistrationNewService.', $key));
        }

        return $value;
    }
}