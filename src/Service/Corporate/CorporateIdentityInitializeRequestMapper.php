<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\DTO\Corporate\CorporateIdentityInitializeRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class CorporateIdentityInitializeRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    /**
     * @param array<string, mixed> $validatedPayload
     */
    public function map(array $validatedPayload): CorporateIdentityInitializeRequestDTO
    {
        $decodedPayload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['getIdentity'] ?? null);

        if (!is_array($decodedPayload)) {
            $this->logger->error('Invalid corporate initialize payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid corporate initialize payload.');
        }

        return CorporateIdentityInitializeRequestDTO::fromArray($decodedPayload);
    }
}
