<?php

namespace App\Helper;

use Symfony\Component\HttpFoundation\Request;

final class UtilityHelper
{

    /**
     * Generate a complete identity key set
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

    public function getRestoreHash()
    {
        return bin2hex($this->generateKey('device'));
    }

    public function generatePin()
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
            return (['error' => 'Invalid JSON payload']);
        }       
        return $payload;
    }

    private static function decodeJson(Request $request): ?array
    {
        $data = json_decode($request->getContent(), true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    public static function validateAuthHeaderFormat($authHeader): array
    {
        if (!preg_match("/^HMAC (\S+):(\S+)$/", $authHeader, $matches)) {
            return (['error' => "Invalid Authorization header format"]);
        }
        return $matches;
    }

    public static function compareExpectations(array $matches, $params, $encryptedData, $iv)
    {
        [$apiKey, $recvSignature] = [trim($matches[1]), trim($matches[2])];

        $expectedKey = $params->get('SERVICE_API_KEY');
        $expectedSecret = $params->get('SERVICE_API_SECRET');

        if ($apiKey !== $expectedKey) {
            return (['error' => "Unknown API key"]);
        }

        $message = "$encryptedData|$iv";
        $expectedSignature = hash_hmac('sha256', $message, $expectedSecret);

        return hash_equals($expectedSignature, $recvSignature) ? ['error' => false] : ['error' => "Invalid HMAC signature"];
    }
         
}
