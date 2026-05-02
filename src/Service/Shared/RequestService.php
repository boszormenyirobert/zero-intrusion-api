<?php

declare(strict_types=1);

namespace App\Service\Shared;

use App\Service\Payload\EncryptedPayloadDecoder;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Request\JsonRequestEnvelopeValidator;
use App\Service\Request\RequestHmacAuthorizationValidator;
use Symfony\Component\HttpFoundation\Request;

class RequestService
{
    public function __construct(
        private readonly JsonRequestEnvelopeValidator $jsonRequestEnvelopeValidator,
        private readonly RequestHmacAuthorizationValidator $requestHmacAuthorizationValidator,
        private readonly EncryptedPayloadDecoder $encryptedPayloadDecoder,
    ) {
    }

    public function validateRequest(Request $request): array
    {
        try {
            return $this->validateRequestOrFail($request);
        } catch (\InvalidArgumentException $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    public function validateRequestOrFail(Request $request): array
    {
        $payload = $this->jsonRequestEnvelopeValidator->validate($request);
        if (array_key_exists('error', $payload)) {
            throw new \InvalidArgumentException((string) $payload['error']);
        }

        return $this->requestHmacAuthorizationValidator->validateOrFail($request, $payload);
    }

    // Every request accepted only from the HUB application with the key: zeroIntrusionProyApi
    public function validPayload(array $payload): ?array
    {
        try {
            return $this->validPayloadOrFail($payload);
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    public function validPayloadOrFail(array $payload): array
    {
        return $this->encryptedPayloadDecoder->decodeOrFail($payload);
    }
}