<?php

namespace App\Service\Crypters;

use App\Entity\AuthBridge;
use App\Entity\identity;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

final class CrypterDatabaseLoginService
{
    private string $key;
    private string $cipher = 'aes-256-cbc';

    public function __construct(
        private ContainerBagInterface $params,
        private LoggerInterface $logger
    ) {}

    public function encryptDataFromArray(array $user): AuthBridge
    {

        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

        $login = new AuthBridge();
        $login->setDomainProcessId($user['domainProcessId']);
        $login->setIv(base64_encode($iv));

        $credential['userName'] = $user['userCredential']->userName;
        $credential['userPassword'] = $user['userCredential']->userPassword;

        $jsonCredential = \json_encode($credential);
        $internaleEncryptedCredential = $this->encryptData($jsonCredential, $iv);
        $login->setUserCredential($internaleEncryptedCredential);

        return $login;
    }


    public function encryptData(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, $this->cipher, $this->key, 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed: ' . openssl_error_string());
        }
        return base64_encode($encrypted);
    }

    public function decryptFromDatabase(AuthBridge $value, string $type = "applications"): AuthBridge|bool
    {
        return match($type) {
            'domain' => $this->decryptDomain($value),
            'applications' => $this->decryptApplications($value),
            default => false,
        };
    }

    private function decryptDomain(AuthBridge $value): AuthBridge|bool
    {
        $iv = base64_decode($value->getIv());
        if (strlen($iv) !== 16) throw new \InvalidArgumentException('Invalid IV length');
        $credential = $value->getUserCredential();
        if (!$credential) return false;
        $decrypted = new AuthBridge();

        $decrypted->setUserCredential($this->decryptData($credential, $iv));
        $description = $value->getDescription();

        if($description){     
            $decrypted->setDescription($this->decryptData($description, $iv));
        }
        $decrypted->setPublicId($value->getPublicId());
        return $decrypted;
    }

    private function decryptApplications(AuthBridge $value): AuthBridge|bool
    {
        $iv = base64_decode($value->getIv());
        if (strlen($iv) !== 16) throw new \InvalidArgumentException('Invalid IV length');

        $applications = $value->getApplications();
        if (!$applications) return false;

        $decrypted = new AuthBridge();
        $decrypted->setApplications($this->decryptData($applications, $iv));

        return $decrypted;
    }

    // Decrypt User Identity from database
    public function decryptFromDatabaseidentity(Identity $value): Identity
    {
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new identity();
        $decrypted->setPrivateId($this->decryptData($value->getPrivateId(), $iv));
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv)); // userIntegritySecret => Secret will be deleted after NFC-card activation
        $decrypted->setPublicId($value->getPublicId());
        $decrypted->setIv($value->getIv());
        $decrypted->setEmail($this->decryptData($value->getEmail(), $iv));
        return $decrypted;
    }

    private function decryptData(string $value, string $iv): string
    {
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $decoded = base64_decode($value);
        $decrypted = openssl_decrypt($decoded, $this->cipher, $this->key, 0, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    public function encyptExtensionIdentityDataObject(array $secretData, $type = "domainProcessId"): AuthBridge
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->key = $this->params->get('DATABASE_HASH_SECRET');

        $encryptedSecret = new AuthBridge();
        if ($type === 'domainProcessId') {
            $encryptedSecret->setDomainProcessId($secretData['domainProcessId']); //Read
        } else if ($type === 'applicationProcessId') {
            $encryptedSecret->setApplicationProcessId($secretData['applicationProcessId']); //Read
        } else if ($type === 'registrationProcessId') {
            $encryptedSecret->setRegistrationProcessId($secretData['registrationProcessId']); //Write -domain and vault
        } else if ($type === 'removeProcessId') {
        //    $encryptedSecret->setRemoveProcessId($secretData['removeProcessId']); //Delete -domain 
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
        $this->key = $this->params->get('DATABASE_HASH_SECRET');
        $iv = base64_decode($value->getIv());

        if (strlen($iv) !== 16) {
            $this->logger->critical(base64_encode($iv) . ' : ' . strlen($iv));
            throw new \InvalidArgumentException('Invalid IV length, expected 16 bytes');
        }

        $decrypted = new AuthBridge();
        $decrypted->setSecret($this->decryptData($value->getSecret(), $iv));

        return $decrypted;
    }
}
