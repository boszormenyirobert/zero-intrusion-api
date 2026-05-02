<?php

declare(strict_types=1);

namespace App\Service\Crypters;

use JsonException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterService
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_LENGTH = 16;

    private string $key;
    private array|string|null $data = null;

    public function __construct(ContainerBagInterface $params)
    {
        $this->key = $params->get('DATA_HASH_SECRET');
    }

    public function setData(array|string $data, bool $forEncryption = true): void
    {
        $this->data = $data;
    }

    public function encrypt(array|string $data): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $plaintext = json_encode($data, JSON_THROW_ON_ERROR);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $this->key, 0, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $payload, bool $decodeJson = false): array|string
    {
        $decrypted = $this->decryptRaw($payload);

        return $decodeJson ? $this->decodeJson($decrypted) : $decrypted;
    }

    public function decryptJson(string $payload): array
    {
        return $this->decodeJson($this->decryptRaw($payload));
    }

    public function encryptData(): string
    {
        return $this->encrypt($this->requireInitializedData());
    }

    public function decryptData(bool $isDTO = false): array|string
    {
        $payload = $this->requireInitializedData();
        if (!is_string($payload)) {
            throw new \RuntimeException('CrypterService encrypted payload is not initialized.');
        }

        return $isDTO ? $this->decryptJson($payload) : $this->decrypt($payload);
    }

    public function decryptStoredJsonData(): array
    {
        $payload = $this->requireInitializedData();
        if (!is_string($payload)) {
            throw new \RuntimeException('CrypterService encrypted payload is not initialized.');
        }

        return $this->decryptJson($payload);
    }

    private function decodeJson(string $data): array
    {
        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON decoding failed: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function decryptRaw(string $payload): string
    {
        $data = base64_decode($payload, true);
        if (!is_string($data) || strlen($data) < self::IV_LENGTH) {
            throw new \RuntimeException('Decryption failed: invalid base64 payload');
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $encrypted = substr($data, self::IV_LENGTH);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    private function requireInitializedData(): array|string
    {
        if ($this->data === null) {
            throw new \RuntimeException('CrypterService data is not initialized.');
        }

        return $this->data;
    }
}
