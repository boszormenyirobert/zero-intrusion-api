<?php

namespace App\Service\Identity;

use Psr\Log\LoggerInterface;
use App\Service\Identity\Database\IdentityDatabaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\Crypters\SodiumService;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Entity\Identity;
use App\Repository\AuthBridgeRepository;
use App\Service\Identity\DTO\IdentityKeyDTO;

final class IdentityService
{
    public function __construct(
        private IdentityDatabaseService $identityDatabaseService,
        private CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
        private IdentityRepository $secretManagerRepository,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private LoggerInterface $logger,
        private AuthBridgeRepository $authBridge,
        private SodiumService $sodiumService
    ) {}


    /**
     * Generates a new key for the user, including a public ID, private ID, and secret.
     * The private ID is encrypted using the secret before being stored in the database.
     *
     * @return IdentityKeyDTO
     */
    public function getKey():IdentityKeyDTO
    {
        $setOfIds = $this->generateSetOfIds();

        // encrypt the privateId with the private secret 
        $identityKey = new IdentityKeyDTO(
            $setOfIds['shared_publicId'],
            $this->sodiumService->sodiumEncrypt($setOfIds['shared_privateId'], $setOfIds['shared_secret']),
            $setOfIds['shared_secret']
        );

        // Encrypt the IdentityKeyDTO object with the global database encryption
        $secret = $this->crypterDatabaseIdentityService->encyptDataObject($identityKey->toArray());
        // Save the encrypted IdentityKeyDTO object in the database
        $this->identityDatabaseService->addIdentity($secret);
        // Set the unencrypted privateId in the IdentityKeyDTO object before returning
        $identityKey->setPrivateId($setOfIds['shared_privateId']);

/**
        $total = $this->secretManagerRepository->count();
        $this->logger->critical("Registrator Public ID: " . $total);
        if($total === 1){
            $first = $this->secretManagerRepository->findBy([], ['id' => 'ASC'], 1)[0] ?? null;
            $this->logger->critical("Registrator Public ID: " . $first->getPublicId());
        }          
 */
        return  $identityKey;
    } 

    private function generateSetOfIds(){
        return [
            'shared_publicId' => base64_encode(random_bytes(35)),
            'shared_privateId' => base64_encode(random_bytes(35)),
            'shared_secret' => base64_encode(random_bytes(35))
        ];
    }

    /**
     * Updates the secret recovery settings for a user.
     *
     * @param array $user An associative array containing user data, including 'publicId', 'privateId', 'email', and 'phone'.
     * @return void
     */             
    public function updateIdentityRecoverySettings($user)
    {
        $this->logger->critical('recovery-settings ', [$user]);   

        // Get user from secretManagerTable => default DB-encrypted
        /** @var Identity */
        $userIdentityObject = $this->secretManagerRepository->findOneBy(['publicId' => $user['publicId']]);
        // Encrypt the user 
        /** @var Identity */
        $decryptedDatabaseIdentity = $this->crypterDatabaseLoginService->decryptFromDatabaseIdentity($userIdentityObject);
        // From Database
        $secret = $decryptedDatabaseIdentity->getSecret();
        // Retrive the decrypted PrivateId from Database --with userIdentity
        $dbDecryptedPrivateId = $this->sodiumService->sodiumDecrypt($decryptedDatabaseIdentity->getPrivateId(), $secret);
        // Retrive the decrypted PrivateId from Request --with userIdentity 
        $requestDecryptedPrivateId = $this->sodiumService->sodiumDecrypt($user['privateId'], $secret);

        if (\strcmp($requestDecryptedPrivateId, $dbDecryptedPrivateId) == 0) {
            /** @varIdentity */
            $encryptedUpdatedIdentityObject = $this->crypterDatabaseIdentityService->encyptUpdateIdentity($decryptedDatabaseIdentity, $user);
            $secretManager = $this->secretManagerRepository->findOneBy(["publicId" => $user['publicId']]);

            $secretManager->setEmail($encryptedUpdatedIdentityObject->getEmail());
            $secretManager->setPhone($encryptedUpdatedIdentityObject->getPhone());
            $secretManager->setPrivacyPolicy($encryptedUpdatedIdentityObject->isPrivacyPolicy());
            $secretManager->setFcmToken($encryptedUpdatedIdentityObject->getFcmToken());
            $this->identityDatabaseService->updateIdentity($secretManager);
        }
    }

    /**
     * Retrieves the secret based on the device data provided.
     *
     * @param array $deviceData An associative array containing 'phone' and 'email'.
     * @return string|null Returns the decrypted secret if found, otherwise null.
     */
    public function getSecret(array $deviceData)
    {
        $encryptedIdentitys = $this->secretManagerRepository->findAll();
        $secret = null;

        foreach ($encryptedIdentitys as $encryptedIdentity) {
            $secretEncrypted = $this->crypterDatabaseIdentityService->decryptFromDatabaseDevice($encryptedIdentity);

            if ($secretEncrypted->getPhone() === $deviceData['phone'] && $secretEncrypted->getEmail() === $deviceData['email']) {
                $secret = $secretEncrypted;
                break;
            }
        }

        return $secret;
    }

    // public function getKeyOriginal()
    // {

    //     $shared_publicId = \bin2hex(base64_encode(random_bytes(35)));
    //     $shared_privateId = \bin2hex(base64_encode(random_bytes(35)));
    //     $shared_secret = \bin2hex(base64_encode(random_bytes(35)));

    //     // encrypt the privateId with the secret before of the usage of the global database entcyption
    //     $privateIdEncryptedByUserIdentity = $this->sodiumService->sodiumEncrypt($shared_privateId, $shared_secret);

    //     $secretDataToSave['publicId'] = $shared_publicId;
    //     $secretDataToSave['privateId'] = $privateIdEncryptedByUserIdentity;
    //     $secretDataToSave['secret'] = $shared_secret;
    //     $secretDataToSave['email'] = "--not-define-registration-process-one";
    //     $secretDataToSave['phone'] = "--not-define-registration-process-one";

    //     $secret = $this->crypterDatabaseIdentityService->encyptDataObject($secretDataToSave);

    //     $this->identityDatabaseService->addIdentity($secret);

    //     $secretData['publicId'] = $shared_publicId;
    //     $secretData['privateId'] = $shared_privateId;
    //     $secretData['secret'] = $shared_secret;
    //     $secretData['email'] = "--not-define-registration-process-one";
    //     $secretData['phone'] = "--not-define-registration-process-one";

    //     return ['privateSecret' => $secretData ];
    // }    
}
