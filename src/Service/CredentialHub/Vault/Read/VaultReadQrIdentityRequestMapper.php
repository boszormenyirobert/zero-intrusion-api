<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class VaultReadQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): VaultReadQrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::VAULT_READ_QR_IDENTITY] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid vault read QR identity payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid vault read QR identity payload.');
        }

        return VaultReadQrIdentityRequestDTO::fromArray($payload);
    }
}