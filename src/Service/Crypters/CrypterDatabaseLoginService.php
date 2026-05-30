<?php

declare(strict_types=1);

namespace App\Service\Crypters;

use App\Entity\AuthBridge;
use App\Entity\Identity;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CrypterDatabaseLoginService
{
    private const CIPHER = 'aes-256-cbc';
    private const IV_LENGTH = 16;

    private string $key;

    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger
    ) {}

    public function encryptDataFromArray(array $user): AuthBridge
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->initializeDatabaseKey();

        $login = new AuthBridge();
        $login->setDomainProcessId($user['domainProcessId']);
        $login->setIv(base64_encode($iv));

        $credential['userName'] = $user['userCredential']->userName;
        $credential['userPassword'] = $user['userCredential']->userPassword;

        $jsonCredential = json_encode($credential, JSON_THROW_ON_ERROR);
        $internaleEncryptedCredential = $this->encryptData($jsonCredential, $iv);
        $login->setUserCredential($internaleEncryptedCredential);

        return $login;
    }


    public function encryptData(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, self::CIPHER, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(AuthBridge $value, string $type = 'applications'): AuthBridge|bool
    {
        if (!in_array($type, ['domain', 'applications'], true)) {
            return false;
        }

        try {
            return $this->decryptFromDatabaseOrFail($value, $type);
        } catch (\UnexpectedValueException) {
            return false;
        }
    }

    public function decryptFromDatabaseOrFail(AuthBridge $value, string $type = 'applications'): AuthBridge
    {
        return match ($type) {
            'domain' => $this->decryptDomainOrFail($value),
            'applications' => $this->decryptApplicationsOrFail($value),
            default => throw new \InvalidArgumentException(sprintf('Unsupported decrypt type: %s', $type)),
        };
    }

    private function decryptDomainOrFail(AuthBridge $value): AuthBridge
    {
        $iv = $this->decodeIv((string) $value->getIv(), 'Invalid IV length');
        $credential = $value->getUserCredential();
        if (!$credential) {
            throw new \UnexpectedValueException('Missing domain credential payload.');
        }

        $decrypted = new AuthBridge();

        $decrypted->setUserCredential($this->decryptData($credential, $iv));
        $description = $value->getDescription();

        if ($description) {
            $decrypted->setDescription($this->decryptData($description, $iv));
        }
        $decrypted->setPublicId($value->getPublicId());

        return $decrypted;
    }

    private function decryptApplicationsOrFail(AuthBridge $value): AuthBridge
    {
        $iv = $this->decodeIv((string) $value->getIv(), 'Invalid IV length');

        $applications = $value->getApplications();
        if (!$applications) {
            throw new \UnexpectedValueException('Missing applications payload.');
        }

        $decrypted = new AuthBridge();
        $decrypted->setApplications($this->decryptData($applications, $iv));

        return $decrypted;
    }

    // Decrypt User Identity from database
    public function decryptFromDatabaseidentity(Identity $value): Identity
    {
        $this->initializeDatabaseKey();
        $iv = $this->decodeIv((string) $value->getIv(), 'Invalid IV length, expected 16 bytes');

        try {
            $decrypted = new Identity();
            $decrypted->setPrivateId($this->decryptData((string) $value->getPrivateId(), $iv));
            $decrypted->setSecret($this->decryptData((string) $value->getSecret(), $iv));
            $decrypted->setPublicId((string) $value->getPublicId());
            $decrypted->setIv((string) $value->getIv());
            $decrypted->setEmail($this->decryptData((string) $value->getEmail(), $iv));
            $decrypted->setCredentialSecret($this->decryptData((string) $value->getCredentialSecret(), $iv));

            return $decrypted;
        } catch (\Exception $e) {
            $this->logger->critical('Decryption error in CrypterDatabaseLoginService: ' . $e->getMessage());

            throw new \RuntimeException('Decryption error in CrypterDatabaseLoginService: ' . $e->getMessage());
        }
    }

    private function decryptData(string $value, string $iv): string
    {
        if ($value === '') {
            return '';
        }

        $this->initializeDatabaseKey();
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

    public function encyptExtensionIdentityDataObject(array $secretData, string $type = 'domainProcessId'): AuthBridge
    {
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        $this->initializeDatabaseKey();

        $encryptedSecret = new AuthBridge();
        if ($type === 'domainProcessId') {
            $encryptedSecret->setDomainProcessId($secretData['domainProcessId']); //Read
        } elseif ($type === 'sessionId') {
            $encryptedSecret->setsessionId($secretData['sessionId']); //Read
        } elseif ($type === 'registrationProcessId') {
            $encryptedSecret->setRegistrationProcessId($secretData['registrationProcessId']); //Write -domain and vault
        } elseif ($type === 'sessionId') {
        //    $encryptedSecret->setSessionId($secretData['sessionId']); //Delete -domain 
        }
        $encryptedSecret->setIv(base64_encode($iv));

        $fields = [
            'secret'   => 'setSecret',
        ];

        foreach ($fields as $field => $setter) {
            if (isset($secretData[$field])) {
                $encryptedSecret->$setter($this->encryptData($secretData[$field], $iv));
            }
        }

        return $encryptedSecret;
    }


    public function decryptFromDatabaseToHmac(AuthBridge $value): AuthBridge
    {
        $this->initializeDatabaseKey();
        $iv = base64_decode((string) $value->getIv(), true);

        if (!is_string($iv) || strlen($iv) !== self::IV_LENGTH) {
            $this->logger->critical(base64_encode($iv) . ' : ' . strlen($iv));

            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new AuthBridge();
        $decrypted->setSecret($this->decryptData((string) $value->getSecret(), $iv));

        return $decrypted;
    }

    private function initializeDatabaseKey(): void
    {
        $this->key = (string) $this->params->get('DATABASE_HASH_SECRET');
    }

    private function decodeIv(string $iv, string $exceptionMessage): string
    {
        $decodedIv = base64_decode($iv, true);

        if (!is_string($decodedIv) || strlen($decodedIv) !== self::IV_LENGTH) {
            throw new \InvalidArgumentException($exceptionMessage);
        }

        return $decodedIv;
    }
}
