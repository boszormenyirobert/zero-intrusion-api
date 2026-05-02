<?php

declare(strict_types=1);

namespace App\Service\Payload;

final class JsonPayloadDecoder
{
    public function decode(mixed $payload): mixed
    {
        if (!is_string($payload) || $payload === '') {
            return null;
        }

        try {
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    public function decodeArray(mixed $payload): ?array
    {
        if (is_array($payload)) {
            return $payload;
        }

        $decoded = $this->decode($payload);

        return is_array($decoded) ? $decoded : null;
    }

    public function requireStringArray(string $payload, string $invalidPayloadMessage, string $nonArrayMessage): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException($invalidPayloadMessage, 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException($nonArrayMessage);
        }

        return $decoded;
    }

    public function requireArray(mixed $payload, string $exceptionMessage): array
    {
        $decoded = $this->decodeArray($payload);

        if ($decoded === null) {
            throw new \InvalidArgumentException($exceptionMessage);
        }

        return $decoded;
    }
}