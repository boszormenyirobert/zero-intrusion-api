<?php

declare(strict_types=1);

namespace App\Service\Restore\Database;

use App\Entity\Restore;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseRestoreService
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_LENGTH = 16;

    private string $key;

    public function __construct(private readonly ContainerBagInterface $params) {}

    public function encyptSourceData(Restore $data): Restore
    {
        return $this->encryptSourceDataOrFail($data);
    }

    public function encryptSourceDataOrFail(Restore $data): Restore
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->initializeKey();

        $encryptedRestore = new Restore();
        $encryptedRestore->setPublicId($data->getPublicId());
        $encryptedRestore->setPin($data->getPin());
        $encryptedRestore->setHash($data->getHash());
        $encryptedRestore->setIv(base64_encode($iv));
        $encryptedRestore->setSecret($this->encryptData($data->getSecret(), $iv));
        $encryptedRestore->setPrivateId($this->encryptData($data->getPrivateId(), $iv));

        return $encryptedRestore;
    }

    private function encryptData(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, self::CIPHER, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(Restore $value): Restore
    {
        $this->initializeKey();
        $iv = $this->decodeIv((string) $value->getIv());

        $decrypted = new Restore();
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setPrivateId($this->decryptData($value->getPrivateId(), $iv));
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));

        return $decrypted;
    }

    private function decryptData(string $value, string $iv): string
    {
        $decoded = base64_decode($value, true);
        if (!is_string($decoded)) {
            throw new \RuntimeException('Decryption failed: invalid base64 payload');
        }

        $decrypted = openssl_decrypt($decoded, self::CIPHER, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    private function initializeKey(): void
    {
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');
    }

    private function decodeIv(string $iv): string
    {
        $decodedIv = base64_decode($iv, true);

        if (!is_string($decodedIv) || strlen($decodedIv) !== self::IV_LENGTH) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        return $decodedIv;
    }
}
