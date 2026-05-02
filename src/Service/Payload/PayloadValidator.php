<?php

declare(strict_types=1);

namespace App\Service\Payload;

use App\Exception\MissingKeyException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class PayloadValidator
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ValidatedPayloadResolver $validatedPayloadResolver,
        private readonly PayloadIntegrityKeyRegistry $payloadIntegrityKeyRegistry
    ) {
    }

    public function validatePayload(Request $request, ?string $key = null): array
    {
        $payload = $this->getValidatedPayload($request);

        if ($key !== null) {
            $this->assertAllowedIntegrityKey($key);
            $this->assertPayloadContainsKey($payload, $key);
        }

        return $payload;
    }

    public function getValidatedPayload(Request $request, ?string $key = null): array
    {
        try {
            $validatedPayload = $this->validatedPayloadResolver->resolve($request);

            if ($key !== null) {
                $this->assertPayloadContainsKey($validatedPayload, $key);
            }

            return $validatedPayload;
        } catch (\Exception $e) {
            $this->logger->error('Payload validation failed', [
                'error' => $e->getMessage(),
                'payload' => $request->attributes->get('json_payload'),
            ]);
            throw $e;
        }
    }

    private function assertAllowedIntegrityKey(string $key): void
    {
        if ($this->payloadIntegrityKeyRegistry->isAllowed($key)) {
            return;
        }

        $this->logger->critical(sprintf('%s is not whitelisted', $key));

        throw new MissingKeyException(sprintf('Not authorized integrity key: %s', $key));
    }

    private function assertPayloadContainsKey(array $payload, string $key): void
    {
        if (array_key_exists($key, $payload)) {
            return;
        }

        $this->logger->critical(sprintf('Property "%s" missing', $key));

        throw new MissingKeyException(sprintf('Property "%s" missing', $key));
    }
}