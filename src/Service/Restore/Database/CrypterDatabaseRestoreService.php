<?php

namespace App\Service\Restore\Database;

use App\Entity\Restore;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseRestoreService
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    public function __construct(private ContainerBagInterface $params) {}

    public function encyptSourceData(Restore $data): Restore
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

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
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(Restore $value): Restore
    {
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new Restore();
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setPrivateId($this->decryptData($value->getPrivateId(), $iv));
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));

        return $decrypted;
    }

    private function decryptData(string $value, string $iv): string
    {
        $decoded = base64_decode($value);
        $decrypted = openssl_decrypt($decoded, $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }
}
