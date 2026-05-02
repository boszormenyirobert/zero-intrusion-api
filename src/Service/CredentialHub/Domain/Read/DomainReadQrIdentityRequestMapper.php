<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Domain\Read\DomainReadQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class DomainReadQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): DomainReadQrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::DOMAIN_READ_QR_IDENTITY] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid domain read QR identity payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid domain read QR identity payload.');
        }

        return DomainReadQrIdentityRequestDTO::fromArray($payload);
    }
}