<?php

declare(strict_types=1);

namespace App\Helper;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class UtilityHelper
{
    private const HMAC_PATTERN = '/^HMAC ([^:]+):([^:]+)(?::(\d+))?$/';
    private const INVALID_JSON_PAYLOAD_ERROR = 'Invalid JSON payload';
    private const INVALID_AUTH_HEADER_ERROR = 'Invalid Authorization header format';
    private const UNKNOWN_API_KEY_ERROR = 'Unknown API key';
    private const INVALID_HMAC_SIGNATURE_ERROR = 'Invalid HMAC signature';

    /**
     * Generate a complete identity key set
     *
     * @return array{corporate_id: string, corporate_id_key: string, corporate_id_secret: string}
     */
    public static function generateIdentity(): array
    {
        return [
            'corporate_id'         => self::generateKey('cid'),
            'corporate_id_key'     => self::generateKey('ckey'),
            'corporate_id_secret'  => self::generateKey('csec'),
        ];
    }

    /**
     * Generate a single hashed value with a prefix
     */
    public static function generateKey(string $prefix): string
    {
        return substr($prefix . '_' . base64_encode(random_bytes(90)), 0, 64);
    }

    public function getRestoreHash(): string
    {
        return bin2hex($this->generateKey('device'));
    }

    public function generatePin(): int
    {
        return \random_int(10000, 99999);
    }

    /**
     * Simple URL builder
     */
    public static function buildPath(string $domain, string $target, string $endpoint): string
    {
        return rtrim($domain, '/') . '/' . trim($target, '/') . '/' . ltrim($endpoint, '/');
    }


    public static function validateJsonFormat(Request $request): array
    {
        $payload = self::decodeJson($request);

        if (!$payload) {
            return ['error' => self::INVALID_JSON_PAYLOAD_ERROR];
        }

        return $payload;
    }

    private static function decodeJson(Request $request): ?array
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    public static function validateAuthHeaderFormat(?string $authHeader): array
    {
        if (!is_string($authHeader) || preg_match(self::HMAC_PATTERN, $authHeader, $matches) !== 1) {
            return ['error' => self::INVALID_AUTH_HEADER_ERROR];
        }

        return $matches;
    }

    public static function compareExpectations(
        array $matches,
        ParameterBagInterface|ContainerBagInterface $params,
        string $encryptedData,
        string $iv
    ): array
    {
        [$apiKey, $recvSignature] = [trim($matches[1]), trim($matches[2])];

        $expectedKey = (string) $params->get('SERVICE_API_KEY');
        $expectedSecret = (string) $params->get('SERVICE_API_SECRET');

        if ($apiKey !== $expectedKey) {
            return ['error' => self::UNKNOWN_API_KEY_ERROR];
        }

        $expectedSignature = hash_hmac('sha256', self::formatSignedMessage($encryptedData, $iv), $expectedSecret);

        return hash_equals($expectedSignature, $recvSignature)
            ? ['error' => false]
            : ['error' => self::INVALID_HMAC_SIGNATURE_ERROR];
    }

    private static function formatSignedMessage(string $encryptedData, string $iv): string
    {
        return sprintf('%s|%s', $encryptedData, $iv);
    }
}
