<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Delete\VaultDeleteQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class VaultDeleteQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): VaultDeleteQrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::VAULT_DELETE_QR_IDENTITY] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid vault delete QR identity payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid vault delete QR identity payload.');
        }

        return VaultDeleteQrIdentityRequestDTO::fromArray($payload);
    }
}