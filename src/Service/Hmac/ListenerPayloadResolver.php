<?php

declare(strict_types=1);

namespace App\Service\Hmac;

use App\DTO\Hmac\ListenerRoutePayloadResolutionDTO;
use App\Service\Crypters\CrypterService;
use App\Service\Payload\JsonPayloadDecoder;

final class ListenerPayloadResolver
{
    public function __construct(
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
        private readonly CrypterService $crypterService,
    ) {
    }

    public function decodeRequestPayload(string $requestContent): ?array
    {
        return $this->jsonPayloadDecoder->decodeArray($requestContent);
    }

    public function hasEncryptedEnvelope(array $payload): bool
    {
        return isset($payload['iv'], $payload['zeroIntrusionProyApi']);
    }

    public function decodeEncryptedPayload(array $payload): ?array
    {
        return $this->jsonPayloadDecoder->decodeArray(
            $this->crypterService->decrypt((string) $payload['zeroIntrusionProyApi'])
        );
    }

    public function resolveRoutePayload(array $data, string $payloadKey): ?array
    {
        if (!array_key_exists($payloadKey, $data) || $data[$payloadKey] === null) {
            return null;
        }

        $routePayload = $data[$payloadKey];
        if (is_string($routePayload)) {
            $routePayload = $this->jsonPayloadDecoder->decodeArray($routePayload);
        }

        return is_array($routePayload) ? $routePayload : null;
    }

    public function resolveDecryptedRoutePayload(array $data, string $payloadKey): ListenerRoutePayloadResolutionDTO
    {
        $payload = $this->resolveRoutePayload($data, $payloadKey);
        $invalidInnerPayload = $payload === null && array_key_exists($payloadKey, $data) && $data[$payloadKey] !== null;

        return new ListenerRoutePayloadResolutionDTO(
            $payload,
            $invalidInnerPayload,
            $payload === null && !$invalidInnerPayload,
        );
    }
}