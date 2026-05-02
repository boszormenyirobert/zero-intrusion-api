<?php

declare(strict_types=1);

namespace App\Service\Identity\Database;

use App\Entity\Identity;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CrypterDatabaseIdentityService
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_LENGTH = 16;

    private string $key;

    public function __construct(private readonly ContainerBagInterface $params) {}

    public function encyptDataObject(array $secretData): Identity
    {
        return $this->encryptDataObjectOrFail($secretData);
    }

    public function encryptDataObjectOrFail(array $secretData): Identity
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->initializeKey();

        $encryptedSecret = new Identity();
        $encryptedSecret->setPublicId($secretData['publicId']);
        $encryptedSecret->setIv(base64_encode($iv));

        $fields = [
            'secret'   => 'setSecret',
            'privateId' => 'setPrivateId',
            'email'   => 'setEmail',
            'phone'   => 'setPhone',
            'credentialSecret' => 'setCredentialSecret',
            'nfcEncryptionKey' => 'setNfcEncryptionKey'
        ];
/**
*        foreach ($fields as $field => $setter) {
*            if (isset($secretData[$field])) {
*                $encryptedSecret->$setter('');
*                if(!$secretData[$field]){
*                    $encryptedSecret->$setter($this->encryptData($secretData[$field], $iv));
*                }
*            }
*        }
*/
        foreach ($fields as $field => $setter) {
            if (!empty($secretData[$field])) { 
                $encryptedSecret->$setter($this->encryptData($secretData[$field], $iv));
            }
        }

        return $encryptedSecret;
    }

    public function encryptData(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, self::CIPHER, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(Identity $value): Identity
    {
        $this->initializeKey();
        $iv = $this->decodeIv((string) $value->getIv());

        $decrypted = new Identity();
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setPrivateId($value->getPrivateId());       
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));

        return $decrypted;
    }

    public function decryptData(string $value, string $iv): string
    {
        $this->initializeKey();
        
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

    public function encyptUpdateIdentity(Identity $decryptedDatabaseIdentity, array $secretData): Identity
    {
        $iv = $this->decodeIv((string) $decryptedDatabaseIdentity->getIv());
        $this->initializeKey();

        $decryptedDatabaseIdentity->setPrivacyPolicy($secretData['privacyPolicy']);
        $fields = [
            'email'   => 'setEmail',
            'phone' => 'setPhone'            
        ];

        foreach ($fields as $field => $setter) {
            if (isset($secretData[$field])) {
                $decryptedDatabaseIdentity->$setter($this->encryptData($secretData[$field], $iv));
            }
        }

        return $decryptedDatabaseIdentity;
    }

    public function decryptFromDatabaseDevice(Identity $value): Identity
    {
        $this->initializeKey();
        $iv = $this->decodeIv((string) $value->getIv());

        $decrypted = new Identity();
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setPrivateId($this->decryptData($value->getPrivateId(), $iv));
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));
        $decrypted->setPhone($this->decryptData($value->getPhone(), $iv));
        $decrypted->setEmail($this->decryptData($value->getEmail(), $iv));

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
