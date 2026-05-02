<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\DTO\Corporate\CorporateFollowUpRequestDTO;
use Psr\Log\LoggerInterface;

class CorporateFollowUpRequestMapper
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $validatedPayload
     */
    public function map(array $validatedPayload): CorporateFollowUpRequestDTO
    {
        if (!array_key_exists('updateIdentity', $validatedPayload)) {
            $this->logger->error('Invalid corporate follow-up payload.', [
                'payload_keys' => array_keys($validatedPayload),
            ]);

            throw new \InvalidArgumentException('Invalid corporate follow-up payload.');
        }

        return new CorporateFollowUpRequestDTO($validatedPayload);
    }
}
