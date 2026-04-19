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
        $this->logger->info('IdentityService initializeIdentity started.', [
            'public_id' => $publicId,
            'scope' => $scope,
            'business_model' => $businessModel,
        ]);

        $keys = $this->generateSslAuthKeys();
        $this->newIdentity = UtilityHelper::generateIdentity();
       
        $this->newIdentity['ssl_public_key'] = $keys['publicKeyPem'];
        $this->newIdentity['ssl_private_key'] =  $keys['privateKeyPem'];

        $this->logger->info('IdentityService initializeIdentity generated raw identity data.', [
            'public_id' => $publicId,
            'scope' => $scope,
            'corporate_id' => $this->newIdentity['corporate_id'] ?? null,
        ]);

        $encryptedIdentity = $this->crypterDatabaseService->encyptDataObject(
            $this->newIdentity
        );

        $this->logger->info('IdentityService initializeIdentity encrypted identity for persistence.', [
            'public_id' => $publicId,
            'scope' => $scope,
            'corporate_id' => $this->newIdentity['corporate_id'] ?? null,
        ]);

        $this->corporateRegistrationDatabaseService->addNewIdentity($encryptedIdentity, $businessModel, $publicId, $scope);

        $this->logger->info('IdentityService initializeIdentity persisted identity.', [
            'public_id' => $publicId,
            'scope' => $scope,
            'corporate_id' => $this->newIdentity['corporate_id'] ?? null,
        ]);
    }

    public function getIdentity(): array
    {
        if (empty($this->newIdentity)) {
            throw new \LogicException('Identity not initialized. Call initializeIdentity() first.');
        }

        $this->logger->info('IdentityService getIdentity returning initialized identity.', [
            'corporate_id' => $this->newIdentity['corporate_id'] ?? null,
        ]);

        unset($this->newIdentity['ssl_private_key']);
        return $this->newIdentity;
    }   

    private  function generateSslAuthKeys(): array
    {
            $this->logger->info('IdentityService generateSslAuthKeys started.');

            $configuredPath = (string) $this->params->get('OPENSSL_CNF');
            $configPath = $this->resolveOpenSslConfigPath($configuredPath);
            $opensslConfig = [
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "private_key_bits" => 2048,
            ];

            if ($configPath !== null) {
                putenv("OPENSSL_CONF=$configPath");
                $opensslConfig['config'] = $configPath;
            }

            $key = openssl_pkey_new($opensslConfig);

            if (!$key && $configPath !== null) {
                $this->logOpenSslErrors('warning');
                $this->logger->warning('OpenSSL key generation with explicit config failed, retrying with OpenSSL defaults.', [
                    'configPath' => $configPath,
                ]);
                $key = openssl_pkey_new([
                    "private_key_type" => OPENSSL_KEYTYPE_RSA,
                    "private_key_bits" => 2048,
                ]);
                $configPath = null;
            }

            if (!$key) {
                $this->logOpenSslErrors();
                throw new \RuntimeException('Key generation failed.');
            }

            if (!openssl_pkey_export($key, $privateKey, null, $configPath !== null ? ["config" => $configPath] : [])) {
                $this->logOpenSslErrors();
                throw new \RuntimeException('Private key export failed.');
            }

            $details = openssl_pkey_get_details($key);
            if (!is_array($details) || !isset($details['key'])) {
                throw new \RuntimeException('Public key extraction failed.');
            }

            $publicKey = $details['key'];

        $keys = [
            'publicKeyPem' => $publicKey,
            'privateKeyPem' => $privateKey,
        ];

        $this->logger->info('IdentityService generateSslAuthKeys completed.', [
            'used_explicit_config' => $configPath !== null,
        ]);

        return $keys;
    }

    private function resolveOpenSslConfigPath(string $configuredPath): ?string
    {
        $candidates = array_filter([
            trim($configuredPath, " \t\n\r\0\x0B\"'"),
            '/etc/ssl/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $this->logger->warning('No valid openssl.cnf file found, falling back to OpenSSL defaults.', [
            'configuredPath' => $configuredPath,
            'candidates' => $candidates,
        ]);

        return null;
    }

    private function logOpenSslErrors(string $level = 'critical'): void
    {
        while ($msg = openssl_error_string()) {
            $this->logger->log($level, "OpenSSL error: $msg");
        }
    }
}
