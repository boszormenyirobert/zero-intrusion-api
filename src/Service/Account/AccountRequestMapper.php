<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\DTO\Account\AccountRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class AccountRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): AccountRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['get_registrated_business'] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid account payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid account payload.');
        }

        return AccountRequestDTO::fromArray($payload);
    }
}
