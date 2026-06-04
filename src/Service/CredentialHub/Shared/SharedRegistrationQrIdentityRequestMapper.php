<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Shared\SharedRegistrationQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;
use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;

class SharedRegistrationQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): ExtensionCredentialRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::SHARED_REGISTRATION_QR_IDENTITY] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid shared registration QR identity payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid shared registration QR identity payload.');
        }

        return ExtensionCredentialRequestDTO::fromArray($payload);
    }
}