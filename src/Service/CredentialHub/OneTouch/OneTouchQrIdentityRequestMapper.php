<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\OneTouch;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\OneTouch\OneTouchQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class OneTouchQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function map(array $validatedPayload): OneTouchQrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload[PayloadKeys::ONE_TOUCH_QR_IDENTITY] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid one-touch QR identity payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid one-touch QR identity payload.');
        }

        return OneTouchQrIdentityRequestDTO::fromArray($payload);
    }
}
