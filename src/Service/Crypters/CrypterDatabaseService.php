<?php

declare(strict_types=1);

namespace App\Service\Crypters;

use App\Entity\CorporateIdentity;
use JsonException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CrypterDatabaseService
{
    private const CIPHER = 'aes-256-cbc'; // update to a more secure cipher: aes-256-gcm
    private const IV_LENGTH = 16;

    private string $key;

    public function __construct(private readonly ContainerBagInterface $params) {}

    public function encyptDataObject(array $identityData): CorporateIdentity
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->initializeDatabaseKey();

        $encryptedIdentity = new CorporateIdentity();
        $encryptedIdentity->setIv(base64_encode($iv));
        $encryptedIdentity->setState('pending');
        $encryptedIdentity->setSslPublicKey($identityData['ssl_public_key']);

        $fields = [
            'corporate_id_key'   => 'setCorporateIdKey',
            'corporate_id_secret' => 'setCorporateIdSecret',
            'ssl_private_key' => 'setSslPrivateKey'
        ];
        
        foreach ($fields as $field => $setter) {
            if (isset($identityData[$field])) {
                $encryptedIdentity->$setter($this->encryptData($identityData[$field], $iv));
            }
        }

        $encryptedIdentity->setCorporateId($identityData['corporate_id']);

        return $encryptedIdentity;
    }

    private function encryptData(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, self::CIPHER, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(CorporateIdentity $value): CorporateIdentity
    {
        $this->initializeDatabaseKey();
        $iv = base64_decode((string) $value->getIv(), true);

        if (!is_string($iv) || strlen($iv) !== self::IV_LENGTH) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new CorporateIdentity();
        $decrypted->setDomain($value->getDomain());
        $decrypted->setCorporateId($value->getCorporateId());
        $decrypted->setCorporateIdKey($this->decryptData($value->getCorporateIdKey(), $iv));
        $decrypted->setCorporateIdSecret($this->decryptData($value->getCorporateIdSecret(), $iv));
        $decrypted->setSslPrivateKey($this->decryptData($value->getSslPrivateKey(), $iv));
        $decrypted->setSslPublicKey($value->getSslPublicKey());
        $decrypted->setCallbackUserLogin($value->getCallbackUserLogin());     
        $decrypted->setCallbackUserRegistration($value->getCallbackUserRegistration()); 
        $decrypted->setIv($value->getIv());   

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

    public function enrcyptUserCredential(array $userCredential, string $iv): array
    {
        return ['encryptedCredential' => $this->encryptUserCredentialOrFail($userCredential, $iv)];
    }

    public function encryptUserCredentialOrFail(array $userCredential, string $iv): string
    {
        $ivDecoded = $this->decodeCredentialIv($iv);
        $this->initializeCredentialKey();

        try {
            $jsonCredential = json_encode($userCredential, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON encoding failed', 0, $exception);
        }

        return $this->encryptData($jsonCredential, $ivDecoded);
    }
    
    // The credential is by the default DB encrypted
    public function decryptUserCredential(string $encryptedCredential, string $iv): string
    {
        try {
            return json_encode($this->decryptUserCredentialOrFail($encryptedCredential, $iv), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON encoding failed', 0, $exception);
        }
    }

    public function decryptUserCredentialOrFail(string $encryptedCredential, string $iv): array
    {
        $this->initializeCredentialKey();
        $ivDecoded = $this->decodeCredentialIv($iv);

        $decryptedJson = $this->decryptData($encryptedCredential, $ivDecoded);
        return $this->decodeJsonArray($decryptedJson);
    }

    private function decodeJsonArray(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON decoding failed: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('JSON decoding failed: Decoded value is not an array');
        }

        return $decoded;
    }

    private function initializeDatabaseKey(): void
    {
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');
    }

    private function initializeCredentialKey(): void
    {
        $this->key = hash('sha256', (string) $this->params->get('DATABASE_HASH_SECRET'), true);
    }

    private function decodeCredentialIv(string $iv): string
    {
        $ivDecoded = base64_decode($iv, true);

        if (!is_string($ivDecoded) || strlen($ivDecoded) !== self::IV_LENGTH) {
            throw new \RuntimeException('Invalid IV length');
        }

        return $ivDecoded;
    }

}
