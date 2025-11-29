<?php

namespace App\Service\AccessRegistry\Database;

use App\Entity\AccessRegistry;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

final class CrypterDatabaseAccessRegistryService
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    public function __construct(
        private ContainerBagInterface $params,
        private LoggerInterface $logger
    ) {}

    public function encyptDataObject(array $userData, $type): AccessRegistry
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

        $encryptedIdentity = new AccessRegistry();
        $encryptedIdentity->setRegistrationState($userData['registrationState']);
        $encryptedIdentity->setPublicId($userData['publicId']);
        $encryptedIdentity->setIv(base64_encode($iv));
        $encryptedIdentity->setRegistrationProcessId($userData['registrationProcessId']);
        $encryptedIdentity->setTargetId($userData['targetId']);

        if(array_key_exists('corporateId', $userData)){
            $encryptedIdentity->setCorporateId($userData['corporateId']);
        }        

        if ($type === 'domain') {
            return $this->encyptDataObjectDomain($userData, $encryptedIdentity, $iv);
        }
        if ($type === 'application') {
            return $this->encyptDataObjecApplication($userData, $encryptedIdentity, $iv);
        }
        return $encryptedIdentity;
    }

    private function encyptDataObjectDomain($userData, $encryptedIdentity, $iv)
    {
        $fields = [
            'domain' => 'setDomain',
            'userCredential' => 'setUserCredential',
            'description' => 'setDescription'
        ];

        foreach ($fields as $field => $setter) {
            if (isset($userData[$field])) {
                $encryptedIdentity->$setter($this->encryptData($userData[$field], $iv));
            }
        }

        return $encryptedIdentity;
    }

    private function encyptDataObjecApplication($userData, $encryptedIdentity, $iv){     
        $fields = [
            'application' => 'setApplication',
            'userCredential' => 'setUserCredential',
            'description' => 'setDescription'
        ];

        foreach ($fields as $field => $setter) {
            if (isset($userData[$field])) {
                $encryptedIdentity->$setter($this->encryptData($userData[$field], $iv));
            }
        }

        return $encryptedIdentity;
    }

    public function encyptDataObjectApplication(array $userData): AccessRegistry
    {        
        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

        $encryptedIdentity = new AccessRegistry();
        $encryptedIdentity->setRegistrationState($userData['registrationState']);
        $encryptedIdentity->setPublicId($userData['publicId']);
        $encryptedIdentity->setRegistrationProcessId($userData['registrationProcessId']);


        $encryptedIdentity->setIv(base64_encode($iv));

        //  $encryptedIdentity->setCorporateId($userData['corporateId']);

        $fields = [
            'application'       => 'setApplication',
            'userCredential'    => 'setUserCredential',
            'description'       => 'setDescription'
        ];

        foreach ($fields as $field => $setter) {
            if (isset($userData[$field])) {
                $encryptedIdentity->$setter($this->encryptData($userData[$field], $iv));
            }
        }

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

    public function decryptFromDatabase(AccessRegistry $value, $type = "domain", $description = true): AccessRegistry|array
    {
        if ($type === "domain") {
            return $this->getDecryptedAccessRegistryDomain($value, $description);

        } else if ($type === "application") {
            $this->logger->critical('Decrypting application data from database for publicId: ' . json_encode($value));
            return $this->getDecryptedAccessRegistryApplication($value);
        }

        return ['error' => 'invalid type'];
    }

    private function getDecryptedAccessRegistryDomain($value, $descriptionExist): AccessRegistry{
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $credential = $value->getUserCredential() ? $value->getUserCredential() : false;        
        $domain = $value->getDomain() ? $value->getDomain() : false;

        if($descriptionExist){
            $description = $value->getDescription() ? $value->getDescription() : false;
        }
        $decrypted = new AccessRegistry();
        if($credential){
            $decrypted->setUserCredential($this->decryptData($credential, $iv));
        }
        if($domain){
            $decrypted->setDomain($this->decryptData( $domain, $iv));
        }
        if($descriptionExist && $description){
            $decrypted->setDescription($this->decryptData($description, $iv));
        }

        $decrypted->setCorporateId($value->getCorporateId());
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setTargetId($value->getTargetId());

        return $decrypted;
    }

    private function getDecryptedAccessRegistryApplication($value): AccessRegistry{
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new AccessRegistry();
        $decrypted->setUserCredential($this->decryptData($value->getUserCredential(), $iv));
        $decrypted->setCorporateId($value->getCorporateId());
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setTargetId($value->getTargetId());

        $decrypted->setApplication($this->decryptData($value->getApplication(), $iv));
        if($value->getDescription()){
            $decrypted->setDescription($this->decryptData($value->getDescription(), $iv));
        }
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
