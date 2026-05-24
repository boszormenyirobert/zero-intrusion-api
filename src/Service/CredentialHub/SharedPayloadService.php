<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Controller\PayloadValidator\PayloadValidator;
use App\Service\AccessRegistry\DTO\DeleteApplicationDto;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class SharedPayloadService
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function decodeJson(array $validatedPayload, string $key): array
    {
        if (!isset($validatedPayload[$key])) {
            throw new \InvalidArgumentException("Payload key '$key' is missing.");
        }

        if (!is_string($validatedPayload[$key])) {
            throw new \InvalidArgumentException("Payload key '$key' must be a JSON string.");
        }

        if ($validatedPayload[$key] === '') {
            throw new \InvalidArgumentException("Payload key '$key' cannot be empty.");
        }

        return $this->jsonPayloadDecoder->requireStringArray(
            $validatedPayload[$key],
            "Payload key \"$key\" must contain valid JSON.",
            "Payload key \"$key\" must decode to an array.",
        );
    }

    public function getApplicationDto(array $user): DeleteApplicationDto
    {
        return new DeleteApplicationDto(
            removeProcessId: $user['removeProcessId'],
            targetId: $user['targetId'],
        );
    }

    public function getPayload(Request $request, string $payloadKey): array
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
        
        return $this->decodePayloadValue($validatedPayload[$payloadKey] ?? null, $payloadKey);
    }

    public function getPayloadOrFail(Request $request, string $payloadKey): array
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);

        return $this->decodePayloadValueOrFail($validatedPayload[$payloadKey] ?? null, $payloadKey);
    }

    public function getProcessId(Request $request, string $payloadKey, bool $fullPayload = false): array|string|false
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
        $payload = $this->decodePayloadValue($validatedPayload[$payloadKey] ?? null, $payloadKey);

        if ($fullPayload) {
            return $payload;
        }

        if ($payload === [] || empty($payload['process'])) {
            $this->logger->error('Getting process ID from payload.', [
                'payload_key' => $payloadKey,
                'payload_content' => $payload,
            ]);
            return false;
        }

        return $payload['process'];
    }
    public function getProcessIdRefact(Request $request, string $payloadKey, bool $fullPayload = false): array|string|false
    {
        $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
                $this->logger->info('validatedPayload', [
            'payload_keys' => array_keys($validatedPayload),
        ]);
        $payload = $this->decodePayloadValue($validatedPayload[$payloadKey] ?? null, $payloadKey);
        
        if ($fullPayload) {
            return $payload;
        }

        if ($payload === [] || empty($payload['domainProcessId'])) {
            return false;
        }

        return $payload['processId'];
    }

    public function getProcessIdOrFail(Request $request, string $payloadKey): string
    {
        $payload = $this->getPayloadOrFail($request, $payloadKey);

        if (!isset($payload['processId']) || !is_string($payload['processId']) || $payload['processId'] === '') {
            throw new \InvalidArgumentException(sprintf('Payload key "%s" must contain processId.', $payloadKey));
        }

        return $payload['processId'];
    }

    private function decodePayloadValue(mixed $payload, string $payloadKey): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload) || $payload === '') {
            return [];
        }

        $decoded = $this->jsonPayloadDecoder->decodeArray($payload);

        if ($decoded === null) {
            $this->logger->warning('CredentialHub shared payload could not be decoded.', [
                'payload_key' => $payloadKey,
            ]);

            return [];
        }

        return $decoded;
    }

    private function decodePayloadValueOrFail(mixed $payload, string $payloadKey): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (!is_string($payload)) {
            throw new \InvalidArgumentException(sprintf("Payload key '%s' must be a JSON string.", $payloadKey));
        }

        if ($payload === '') {
            throw new \InvalidArgumentException(sprintf("Payload key '%s' cannot be empty.", $payloadKey));
        }

        return $this->jsonPayloadDecoder->requireStringArray(
            $payload,
            sprintf('Payload key "%s" must contain valid JSON.', $payloadKey),
            sprintf('Payload key "%s" must decode to an array.', $payloadKey),
        );
    }
}