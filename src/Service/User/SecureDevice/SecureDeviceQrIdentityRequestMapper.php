<?php

declare(strict_types=1);

namespace App\Service\User\SecureDevice;

use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class SecureDeviceQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): QrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['secure_device_registration'] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid secure device registration payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid secure device registration payload.');
        }

        return new QrIdentityRequestDTO($payload, 'domainProcessId');
    }
}
