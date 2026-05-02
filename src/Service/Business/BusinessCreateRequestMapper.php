<?php

declare(strict_types=1);

namespace App\Service\Business;

use App\DTO\Business\BusinessCreateRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class BusinessCreateRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    /**
     * @param array<string, mixed> $validatedPayload
     */
    public function map(array $validatedPayload): BusinessCreateRequestDTO
    {
        $decodedPayload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['business_create'] ?? null);

        if (!is_array($decodedPayload)) {
            $this->logger->error('Invalid business create payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid business create payload.');
        }

        return BusinessCreateRequestDTO::fromArray($decodedPayload);
    }
}
