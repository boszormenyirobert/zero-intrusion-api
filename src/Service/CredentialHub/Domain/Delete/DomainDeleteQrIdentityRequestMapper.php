<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class DomainDeleteQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): DomainDeleteQrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::DOMAIN_DELETE_QR_IDENTITY] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid domain delete QR identity payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid domain delete QR identity payload.');
        }

        return DomainDeleteQrIdentityRequestDTO::fromArray($payload);
    }
}