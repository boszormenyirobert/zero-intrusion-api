<?php

namespace App\Service\AuthBridge\AuthBridgeHandler;

use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use Psr\Log\LoggerInterface;
use App\Repository\IdentityRepository;
use App\Service\AuthBridge\DTO\ValidationDTO;

class ValidationHandler
{
    public function __construct(
        private IdentityRepository $identityRepository,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private LoggerInterface $logger,
        private SodiumService $sodiumService,
    ) {}

    // Each user-credential double encrypted
    // First with user secret and second with general database key
    // This function validate the request by privateId, and return with the user secret if valid
    public function checkExtensionRequestValidation(array $user): ValidationDTO
    {
        $userSecretObject = $this->identityRepository->findOneBy(['publicId' => $user['publicId']]);
        
        // This is the secret which saved in the mobile application (after NFC verification from the DB will be deleted)
        // Only with this secret can be the user-credential decrypted
        
        // The user-secret used to decrypt the privateId from the mobile request and also the encrypt the user-credentials => TODO: ADDING SEPARATE HMAC_CREDENTIAL_SECRET
        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($userSecretObject);
        $userSecret = $decrypted->getSecret();

        // Decrypt the user secret by the general database key
        $dbPrivateId = $this->sodiumService->sodiumDecrypt($decrypted->getPrivateId(), $userSecret);
        $requestPrivateId = $this->sodiumService->sodiumDecrypt($user['privateId'], $userSecret);
        
        if (\strcmp($requestPrivateId, $dbPrivateId) === 0) {
            $this->logger->critical('PrivateId is valid');
            
            return new ValidationDTO(true, $userSecret);
        }

        $this->logger->critical('Unvalid PrivateId');
        return new ValidationDTO(false);
    }
}
