<?php

declare(strict_types=1);

namespace App\Service\Payload;

use App\Service\Crypters\CrypterService;
use Psr\Log\LoggerInterface;

class EncryptedPayloadDecoder
{
    public function __construct(
        private readonly CrypterService $crypterService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function decode(array $payload): ?array
    {
        try {
            return $this->decodeOrFail($payload);
        } catch (\UnexpectedValueException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeOrFail(array $payload): array
    {
        $this->logger->info('RequestService validPayload decrypting payload.', [
            'payload_keys' => array_keys($payload),
        ]);

        $validatedPayload = $this->jsonPayloadDecoder->decodeArray(
            $this->crypterService->decrypt((string) $payload['zeroIntrusionProyApi'])
        );

        $this->logger->info('RequestService validPayload decrypted payload.', [
            'validated_payload_keys' => is_array($validatedPayload) ? array_keys($validatedPayload) : [],
        ]);

        if (!is_array($validatedPayload)) {
            throw new \UnexpectedValueException('Invalid encrypted payload.');
        }

        return $validatedPayload;
    }
}