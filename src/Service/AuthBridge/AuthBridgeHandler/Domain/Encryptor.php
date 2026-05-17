<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler\Domain;

use App\Entity\AccessRegistry;
use App\Entity\AuthBridge;
use App\Repository\AccessRegistryRepository;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor as ApplicationEncryptor;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use JsonException;
use Psr\Log\LoggerInterface;
use App\Service\Firebase\FirebaseService;
use App\DTO\QR\StoreDTO;

class Encryptor
{
    public function __construct(
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseUserService,
        private readonly SodiumService $sodiumService,
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private readonly LoginDatabaseService $loginDatabaseService,
        private readonly LoggerInterface $logger,
        private readonly ApplicationEncryptor $applicationEncryptor,
        private readonly FirebaseService $firebaseService,
        private readonly ProcessStateCacheService $processStateCacheService
    ) {}

    // Set decrypted values for domain login
    public function setDecryptedValuesForDomain(array $user): bool
    {
       // $this->logger->info(json_encode($user));
       //  $credentialsCollection = $this->formatCredentials($user, 'decrypted');

       // $credentialsCollection = $user['credentials'];

        if (!$user) {
            return false;
        }
        
        return $this->updateLoginEntry($user);
    }

    public function setDecryptedUserIdentity(array $user): bool
    {
        $authBridge = $this->authBridgeRepository->findOneBy(['oneTouchProcessId' => $user['oneTouchProcessId']]);
        if (!$authBridge) {
            $this->logger->critical("No user login found for oneTouchProcessId: " . $user['oneTouchProcessId']);
            return false;
        }

        $identity = [
            'publicId' => $user['publicId'],
            'email' => $user['email'],
        ];

        $authBridge->setUserIdentity($this->encodeJson($identity));
        $this->writeLoginEntryInRedis($user['oneTouchProcessId'], $authBridge);

        return true;
    }
    
    private function writeLoginEntryInRedis(string $processId, AuthBridge $authBridge): void
    {
        $this->processStateCacheService->set(
            $processId,
            $this->encodeJson($authBridge->toCacheArray(), JSON_UNESCAPED_UNICODE),
            300
        );
    }

    public function preapredCredentials(StoreDTO $storeDTO): array{
        $pages = $this->accessRegistryRepository->findBy(['publicId' =>  $storeDTO->userPublicId]);                               

        $apps = $this->extractCredentialsForDomain($pages, $storeDTO->domain);
        $credentials = $this->formatCredentials(['credentials' => $apps], 'credential'); 
        return $credentials;
    }

    // Extract the credentials, description and targetId from the database by publicId and domain
    // Decrypt by database key    
    public function getDecryptedCredentials(array $user): ?array
    {
        $pages = $this->accessRegistryRepository->findBy(
            ['publicId' => $user['publicId']
        ]);

        $apps = $this->extractCredentialsForDomain($pages, $user['domain']);

        if (!$apps || (sizeof($apps) === 1 &&!$apps[0]['credential'])) {
            $this->logger->critical("No matching credential found for domain: " . $user['domain']);
            return [];
        }

        return $this->formatCredentials(['credentials' => $apps], 'credential');       
    }   
        
    private function formatCredentials(array $user, string $credentialKey): array
    {
        return array_map(fn($app) => [
            $credentialKey   => $app['credential'],
            'description' => $app['description'],
            'targetId'    => $app['targetId'],
        ], $user['credentials']);
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

    // Update the login entry with the by user secret decrypted credentials
    // Write the encrypted credentials in Redis cache => key is the processId. 
    // To the encryption use the iv saved in the AuthBridge entry and the general database secret
    private function updateLoginEntry(array $user): bool
    {
         $this->logger->critical("updateLoginEntry: " . $user['domainProcessId']);

        $authBridge = $this->authBridgeRepository->findOneBy(['domainProcessId' => $user['domainProcessId']]);

        if (!$authBridge) {
            $this->logger->critical("No user login found for domainProcessId: " . $user['domainProcessId']);
            return false;
        }

        /**
        $iv = base64_decode($authBridge->getIv());
        $dbEncryptedCredentials = [];
        foreach ($credentialsCollection as $credentialData) {
            $encryptedCredential = new AccessRegistry();
            $encryptedCredential->setUserCredential($credentialData['decrypted']);
            $encryptedCredential->setDescription($credentialData['description']);
            $encryptedCredential->setTargetId($credentialData['targetId']);
            $dbEncryptedCredentials[] = $encryptedCredential; 
        } 


        $databaseEncryptedCredentialsList = $this->applicationEncryptor->encrypt($dbEncryptedCredentials, $iv);
         */
        $authBridge->setProcessState(true);
        //$authBridge->setApplications($databaseEncryptedCredentialsList);

        $this->loginDatabaseService->addUserLogin($authBridge); 

       // $this->writeLoginEntryInRedis($user['domainProcessId'], $credentialsCollection);
            $this->processStateCacheService->set(
            $user['domainProcessId'], json_encode($user)
        );

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

    private function encodeJson(array $payload, int $flags = 0): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | $flags);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON encoding failed.', 0, $exception);
        }
    }
}
