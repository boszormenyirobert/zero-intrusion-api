<?php

namespace App\Service\Crypters;

use Psr\Log\LoggerInterface;


class SodiumService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    // Encrypt user credential
    public function sodiumDecrypt(string $decryptedCredential, $secret)
    {
        $encrypted = base64_decode($decryptedCredential);
        if ($encrypted === false) {
            $this->logger->critical('Invalid base64 input for decryption');
            throw new \RuntimeException('Invalid base64 input for decryption');
        }
        if (strlen($encrypted) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            $this->logger->critical('Encrypted data is too short');
            throw new \RuntimeException('Encrypted data is too short');
        }

        $key = sodium_crypto_generichash($secret, "", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = mb_substr($encrypted, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, "8bit");
        $cipher = mb_substr($encrypted, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, "8bit");
        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        return $plaintext;
    }

    public function sodiumEncrypt(string $data, $secret)
    {
        $key = sodium_crypto_generichash($secret, "", SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($data, $nonce, $key);
        $base64 =  base64_encode($nonce . $cipher);
        
        return $base64;
    }
}
