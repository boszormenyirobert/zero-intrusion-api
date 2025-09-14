<?php

namespace App\Service\Corporate;

use App\Helper\UtilityHelper;
use App\Service\Crypters\CrypterDatabaseService;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

class IdentityService
{
    private array $newIdentity = [];

    public function __construct(
        private ContainerBagInterface $params,
        private CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private CrypterDatabaseService $crypterDatabaseService,
        private LoggerInterface $logger
    ) {}


    public function initializeIdentity($businessModel, $publicId, $scope): void
    {
        $keys = $this->generateSslAuthKeys();
        $this->newIdentity = UtilityHelper::generateIdentity();
       
        $this->newIdentity['ssl_public_key'] = $keys['publicKeyPem'];
        $this->newIdentity['ssl_private_key'] =  $keys['privateKeyPem'];

        $encryptedIdentity = $this->crypterDatabaseService->encyptDataObject(
            $this->newIdentity,
            $this->params
        );

        $this->corporateRegistrationDatabaseService->addNewIdentity($encryptedIdentity, $businessModel, $publicId, $scope);
    }

    public function getIdentity(): array
    {
        if (empty($this->newIdentity)) {
            throw new \LogicException('Identity not initialized. Call initializeIdentity() first.');
        }
        unset($this->newIdentity['ssl_private_key']);
        return $this->newIdentity;
    }   

    private  function generateSslAuthKeys(): array
    {
            $configPath = $this->params->get('OPENSSL_CNF');;

            if (!file_exists($configPath)) {
                $this->logger->critical("Nem találom az openssl.cnf fájlt: $configPath\n");
            }

            putenv("OPENSSL_CONF=$configPath");

            $key = openssl_pkey_new([
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "private_key_bits" => 2048,
                "config" => $configPath,
            ]);

            if (!$key) {
                while ($msg = openssl_error_string()) {
                    $this->logger->critical("OpenSSL hiba: $msg\n");
                }
                $this->logger->critical("Kulcs generálás sikertelen.\n");
            }

            openssl_pkey_export($key, $privateKey, null, ["config" => $configPath]);
            $details = openssl_pkey_get_details($key);
            $publicKey = $details['key'];

        $keys = [
            'publicKeyPem' => $publicKey,
            'privateKeyPem' => $privateKey,
        ];

        return $keys;
    }          
}
