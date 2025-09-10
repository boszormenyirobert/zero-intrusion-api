<?php

namespace App\Service\Crypters;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterService
{
    private const CIPHER = 'aes-256-cbc';

    private string $key;
    private string $iv;
    private array|string $data;

    public function __construct(ContainerBagInterface $params)
    {
        $this->key = $params->get('DATA_HASH_SECRET');
    }

    public function setData(array|string $data, bool $forEncryption = true): void
    {
        $this->data = $data;
        $this->setIv($forEncryption, is_string($data) ? $data : '');        
    }

    public function encryptData(): string
    {
        $plaintext = json_encode($this->data);
        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $this->key, 0, $this->iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($this->iv . $encrypted);
    }

    public function decryptData(bool $isDTO = false): array|string
    {
        $data = base64_decode($this->data);
        $this->setIv(false, $data);

        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $this->key, 0, $this->iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $isDTO ? $this->decodeJson($decrypted) : $decrypted;
    }

    private function setIv(bool $encrypt = true, string $data = ''): void
    {
        $this->iv = $encrypt ? openssl_random_pseudo_bytes(16) : substr($data, 0, 16);
    }

    private function decodeJson(string $data): array
    {
        $decoded = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON decoding failed: ' . json_last_error_msg());
        }
        return $decoded;
    }
}
