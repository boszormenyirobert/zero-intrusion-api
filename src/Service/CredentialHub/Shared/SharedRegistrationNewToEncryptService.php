<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Controller\CredentialHub\Shared\SharedRegistrationService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewToEncryptResultDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Symfony\Component\HttpFoundation\Request;

class SharedRegistrationNewToEncryptService
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly SharedRegistrationService $sharedRegistrationService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function handle(Request $request): SharedRegistrationNewToEncryptResultDTO
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_new_to_encrypt');
        $user = $this->jsonPayloadDecoder->requireArray(
            $validatedPayload['shared_registration_new_to_encrypt'] ?? null,
            'Invalid shared registration new-to-encrypt payload.'
        );

        return new SharedRegistrationNewToEncryptResultDTO(
            $this->sharedRegistrationService->getUserCredentialFromAuthBridge($user['registrationProcessId']),
            ''
        );
    }
}