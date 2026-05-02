<?php

declare(strict_types=1);

namespace App\Service\Device\Restore;

use App\DTO\Device\Restore\ReplaceDeviceRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class ReplaceDeviceRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): ReplaceDeviceRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['replaceDevice'] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid replace device payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid replace device payload.');
        }

        return ReplaceDeviceRequestDTO::fromArray($payload);
    }
}
