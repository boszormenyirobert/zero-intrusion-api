<?php

declare(strict_types=1);

namespace App\Service\Device\Restore;

use App\DTO\Device\Restore\ReplaceDevicePinRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class ReplaceDevicePinRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): ReplaceDevicePinRequestDTO
    {
        if (!array_key_exists('restorePin', $validatedPayload)) {
            $this->logger->error('Invalid replace device pin payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid replace device pin payload.');
        }

        $payload = $validatedPayload;
    $payload['restorePin'] = $this->jsonPayloadDecoder->decodeArray($validatedPayload['restorePin']);

        if (!is_array($payload['restorePin'])) {
            $this->logger->error('Invalid replace device pin payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid replace device pin payload.');
        }

        return new ReplaceDevicePinRequestDTO($payload);
    }
}
