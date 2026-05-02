<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewResultDTO;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\Payload\JsonPayloadDecoder;
use Symfony\Component\HttpFoundation\Request;

class SharedRegistrationNewService
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly AccessRegistryRegistrationService $accessRegistryRegistrationService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function handle(Request $request): SharedRegistrationNewResultDTO
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new');
        $user = $this->jsonPayloadDecoder->requireArray(
            $validatedPayload['shared_registration_new'] ?? null,
            'Invalid shared registration new payload.'
        );
        $type = $user['type'];
        $key = in_array($type, ['registration-domain', 'system_hub_registration'], true) ? 'domain' : 'application';
        $registeredUser = $this->accessRegistryRegistrationService->addAccessRegistry($user, $key, $type === 'system_hub_registration');

        if ($type === 'system_hub_registration') {
            $this->accessRegistryRegistrationService->sendNotification($registeredUser, $user);
        }

        return new SharedRegistrationNewResultDTO($registeredUser, '');
    }
}