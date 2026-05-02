<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\Database;

use App\Entity\AccessRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseAccessRegistryService
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_LENGTH = 16;

    private string $key;

    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger
    ) {}

    public function encyptDataObject(array $userData, string $type): AccessRegistry
    {
        return $this->encryptDataObjectOrFail($userData, $type);
    }

    public function encryptDataObjectOrFail(array $userData, string $type): AccessRegistry
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');

        $encryptedIdentity = new AccessRegistry();
        $encryptedIdentity->setRegistrationState($userData['registrationState']);
        $encryptedIdentity->setPublicId($userData['publicId']);
        $encryptedIdentity->setIv(base64_encode($iv));
        $encryptedIdentity->setRegistrationProcessId($userData['registrationProcessId']);
        $encryptedIdentity->setTargetId($userData['targetId']);

        if (array_key_exists('corporateId', $userData)) {
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

    private function encyptDataObjectDomain(array $userData, AccessRegistry $encryptedIdentity, string $iv): AccessRegistry
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

    private function encyptDataObjecApplication(array $userData, AccessRegistry $encryptedIdentity, string $iv): AccessRegistry
    {
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
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');

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
        $encrypted = openssl_encrypt($value, self::CIPHER, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabaseOrFail(AccessRegistry $value, string $type = 'domain', bool $description = true): AccessRegistry
    {
        if ($type === 'domain') {
            return $this->getDecryptedAccessRegistryDomain($value, $description);
        }

        if ($type === 'application') {
            return $this->getDecryptedAccessRegistryApplication($value);
        }

        throw new \InvalidArgumentException('invalid type');
    }

    public function decryptFromDatabase(AccessRegistry $value, string $type = 'domain', bool $description = true): AccessRegistry|array
    {
        if ($type === 'domain' || $type === 'application') {
            return $this->decryptFromDatabaseOrFail($value, $type, $description);
        }

        return ['error' => 'invalid type'];
    }

    private function getDecryptedAccessRegistryDomain(AccessRegistry $value, bool $descriptionExist): AccessRegistry
    {
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');
        $iv = $this->decodeIv((string) $value->getIv());

        $credential = $value->getUserCredential() ? $value->getUserCredential() : false;
        $domain = $value->getDomain() ? $value->getDomain() : false;

        if ($descriptionExist) {
            $description = $value->getDescription() ? $value->getDescription() : false;
        }
        $decrypted = new AccessRegistry();
        if ($credential) {
            $decrypted->setUserCredential($this->decryptData($credential, $iv));
        }
        if ($domain) {
            $decrypted->setDomain($this->decryptData($domain, $iv));
        }
        if ($descriptionExist && $description) {
            $decrypted->setDescription($this->decryptData($description, $iv));
        }

        $decrypted->setCorporateId($value->getCorporateId());
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setTargetId($value->getTargetId());

        return $decrypted;
    }

    private function getDecryptedAccessRegistryApplication(AccessRegistry $value): AccessRegistry
    {
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');
        $iv = $this->decodeIv((string) $value->getIv());

        $decrypted = new AccessRegistry();
        $decrypted->setUserCredential($this->decryptData($value->getUserCredential(), $iv));
        $decrypted->setCorporateId($value->getCorporateId());
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setTargetId($value->getTargetId());

        $decrypted->setApplication($this->decryptData($value->getApplication(), $iv));
        if ($value->getDescription()) {
            $decrypted->setDescription($this->decryptData($value->getDescription(), $iv));
        }
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

    private function decodeIv(string $iv): string
    {
        $decodedIv = base64_decode($iv, true);

        if (!is_string($decodedIv) || strlen($decodedIv) !== self::IV_LENGTH) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        return $decodedIv;
    }
}
