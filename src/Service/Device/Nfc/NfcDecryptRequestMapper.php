<?php

declare(strict_types=1);

namespace App\Service\Device\Nfc;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\Device\Nfc\NfcDecryptRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class NfcDecryptRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): NfcDecryptRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::API_NFC_DECRYPT] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid NFC decrypt payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid NFC decrypt payload.');
        }

        return NfcDecryptRequestDTO::fromArray($payload);
    }
}
