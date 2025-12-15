<?php

namespace App\Service\Identity\Database;

use App\Entity\Identity;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseIdentityService
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    public function __construct(private ContainerBagInterface $params) {}

    public function encyptDataObject(array $secretData): Identity
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

        $encryptedSecret = new Identity();
        $encryptedSecret->setPublicId($secretData['publicId']);
        $encryptedSecret->setIv(base64_encode($iv));

        $fields = [
            'secret'   => 'setSecret',
            'privateId' => 'setPrivateId',
            'email'   => 'setEmail',
            'phone'   => 'setPhone',
            'credentialSecret' => 'setCredentialSecret'
        ];

        foreach ($fields as $field => $setter) {
            if (isset($secretData[$field])) {
                $encryptedSecret->$setter('');
                if(!$secretData[$field]){
                    $encryptedSecret->$setter($this->encryptData($secretData[$field], $iv));
                }
            }
        }

        return $encryptedSecret;
    }

    public function encryptData(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(Identity $value): Identity
    {
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new Identity();
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setPrivateId($value->getPrivateId());       
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));

        return $decrypted;
    }

    public function decryptData(string $value, string $iv): string
    {
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        
        $decoded = base64_decode($value);
        $decrypted = openssl_decrypt($decoded, $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    public function encyptUpdateIdentity(Identity $decryptedDatabaseIdentity, array $secretData): Identity
    {
        $iv = base64_decode($decryptedDatabaseIdentity->getIv());
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

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
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new Identity();
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setPrivateId($this->decryptData($value->getPrivateId(), $iv));
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));
        $decrypted->setPhone($this->decryptData($value->getPhone(), $iv));
        $decrypted->setEmail($this->decryptData($value->getEmail(), $iv));

        return $decrypted;
    }
}
