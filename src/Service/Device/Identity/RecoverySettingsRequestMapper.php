<?php

declare(strict_types=1);

namespace App\Service\Device\Identity;

use App\DTO\Device\Identity\RecoverySettingsRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class RecoverySettingsRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): RecoverySettingsRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['recoverySettings'] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid recovery settings payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid recovery settings payload.');
        }

        return RecoverySettingsRequestDTO::fromArray($payload);
    }
}
