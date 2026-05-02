<?php

declare(strict_types=1);

namespace App\Service\User\Login;

use App\DTO\User\Login\LoginQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;

class LoginQrIdentityRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    /**
     * @param array<string, mixed> $validatedPayload
     */
    public function map(array $validatedPayload): LoginQrIdentityRequestDTO
    {
        $payload = $this->jsonPayloadDecoder->decodeArray($validatedPayload['user_login'] ?? null);

        if (!is_array($payload)) {
            $this->logger->error('Invalid user login payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid user login payload.');
        }

        return LoginQrIdentityRequestDTO::fromArray($payload);
    }
}
