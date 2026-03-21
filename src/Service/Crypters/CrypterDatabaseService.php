<?php

namespace App\Service\Crypters;

use App\Entity\CorporateIdentity;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseService
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    public function __construct(private ContainerBagInterface $params) {}

    public function encyptDataObject(array $identityData): CorporateIdentity
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

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
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(CorporateIdentity $value): CorporateIdentity
    {
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
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
        $decoded = base64_decode($value);
        $decrypted = openssl_decrypt($decoded, $this->cipher, $this->key, 0, $iv);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    public function enrcyptUserCredential(array $userCredential, string $iv)
    {
       $ivDecoded = base64_decode($iv);
        $this->key = hash('sha256', $this->params->get('DATABASE_HASH_SECRET'), true);

        $jsonCredential = json_encode($userCredential);
        if ($jsonCredential === false) {
            throw new \RuntimeException('JSON encoding failed');
        }
        
        return ['encryptedCredential' =>$this->encryptData($jsonCredential, $ivDecoded)];
    }
    
    // The credential is by the default DB encrypted
    public function decryptUserCredential(string $encryptedCredential, string $iv)
    {        
        $this->key = hash('sha256', $this->params->get('DATABASE_HASH_SECRET'), true);
        $ivDecoded = base64_decode($iv);

        if (strlen($ivDecoded) !== 16) {
            throw new \RuntimeException('Invalid IV length');
        }

            $decryptedJson = $this->decryptData($encryptedCredential, $ivDecoded);
            $decoded = json_decode($decryptedJson, true);

            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('JSON decoding failed: ' . json_last_error_msg());
            }

        return $decryptedJson;
    }
}
