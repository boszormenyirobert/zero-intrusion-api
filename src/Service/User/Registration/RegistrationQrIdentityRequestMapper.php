<?php

declare(strict_types=1);

namespace App\Service\User\Registration;

use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class RegistrationQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): QrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['user_registration'] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid user registration payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid user registration payload.');
        }

        return new QrIdentityRequestDTO($payload, 'registrationProcessId');
    }
}
