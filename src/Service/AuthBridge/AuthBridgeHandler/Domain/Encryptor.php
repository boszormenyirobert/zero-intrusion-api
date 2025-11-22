<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Domain;

use App\Entity\AccessRegistry;
use App\Repository\AccessRegistryRepository;
use App\Repository\AuthBridgeRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Psr\Log\LoggerInterface;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor as ApplicationEncryptor;

class Encryptor
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository,
        private CrypterDatabaseAccessRegistryService $crypterDatabaseUserService,
        private SodiumService $sodiumService,
        private AuthBridgeRepository $authBridgeRepository,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private LoginDatabaseService $loginDatabaseService,
        private LoggerInterface $logger,
        private ApplicationEncryptor $applicationEncryptor
    ) {}

    // Set decrypted values for domain login
    public function setDecryptedValuesForDomain(array $user, string $userSecret): bool
    {
        $credentialsCollection = $this->findDecryptedCredential($user, $userSecret);

        if (!$credentialsCollection) {
            return false;
        }
        
        return $this->updateLoginEntry($user, $credentialsCollection);
    }
        
    private function findDecryptedCredential(array $user, string $userSecret): array
    {
        $pages = $this->accessRegistryRepository->findBy(
            ['publicId' => $user['publicId']
        ]);
        
        $apps = $this->extractCredentialsForDomain($pages, $user['domain']);

        if (!$apps || (sizeof($apps) === 1 &&!$apps[0]['credential'])) {
            $this->logger->critical("No matching credential found for domain: " . $user['domain']);
            return [];
        }

        $decryptedCredentials = [];
        foreach ($apps as $app) {
            $this->logger->info("Decrypting credential for credential: " . $user['credential']);
            $decrypted = $this->sodiumService->sodiumDecrypt($app['credential'], $userSecret);
            $decryptedCredentials[] = [
                'decrypted' => $decrypted,
                'description' => $app['description'],
                'targetId' => $app['targetId']
            ];
        }
        
        return $decryptedCredentials;
    }

    public function findDecryptedCredentialForWeb(array $user, string $userSecret): ?array
    {
        $pages = $this->accessRegistryRepository->findBy([
                'publicId' => $user['publicId'],
                'corporateId' => $user['corporateId']
                ]);

        $app = $this->extractCredentialForWeb($pages, $user['domain']);

        if (!$app || !$app['credential']) {
            $this->logger->critical("No matching credential found for domain: " . $user['domain']);
            return null;
        }

        $decrypted = $this->sodiumService->sodiumDecrypt($app['credential'], $userSecret);
        return ['decrypted' => $decrypted];
    }    

    // Update the login entry with decrypted credentials
    // Write back the encrypted credentials to the database
    // To the encryption use the iv saved in the AuthBridge entry and the general database key
    private function updateLoginEntry(array $user, array $credentialsCollection): bool
    {
        $authBridge = $this->authBridgeRepository->findOneBy(['domainProcessId' => $user['domainProcessId']]);

        if (!$authBridge) {
            $this->logger->critical("No user login found for domainProcessId: " . $user['domainProcessId']);
            return false;
        }

        $iv = base64_decode($authBridge->getIv());
        $dbEncryptedCredentials = [];
        foreach ($credentialsCollection as $credentialData) {
            $encryptedCredential = new AccessRegistry();
            $encryptedCredential->setUserCredential($credentialData['decrypted']);
            $encryptedCredential->setDescription($credentialData['description']);
            $encryptedCredential->setTargetId($credentialData['targetId']);
            $dbEncryptedCredentials[] = $encryptedCredential; 
        } 
        
        $databaseEncryptedCredentialsList = $this->applicationEncryptor->encrypt($credentialsCollection, $iv);

        $authBridge->setProcessState(true);
        $authBridge->setApplications($databaseEncryptedCredentialsList);
        $this->loginDatabaseService->addUserLogin($authBridge); 

        return true;
    }

    // Return with all matching credentials for the domain by publicId
    private function extractCredentialsForDomain(array $getPages, string $targetDomain): array
    {
        $results = [];
        
        foreach ($getPages as $userPage) {
            if ($userPage->getDomain() !== null) {
                $decrypted = $this->crypterDatabaseUserService->decryptFromDatabase($userPage);
                if ($decrypted && $decrypted->getDomain() === $targetDomain) {
                    $results[] = [
                        'credential' => $decrypted->getUserCredential(),
                        'description' => $decrypted->getDescription(),
                        'targetId' => $decrypted->getTargetId()
                    ];
                }
            }
        }
        
        return $results;
    }

    private function extractCredentialForWeb(array $getPages, string $targetDomain): ?array
    {
        foreach ($getPages as $userPage) {
            if ($userPage->getDomain() !== null) {
                $decrypted = $this->crypterDatabaseUserService->decryptFromDatabase($userPage, 'domain', false);
                        
                if ($decrypted && $decrypted->getDomain() === $targetDomain) {
                    return ['credential' => $decrypted->getUserCredential()];
                }
            }
        }
        $this->logger->critical('credential not found to the domain'); 
        return null;       
    }    
}
