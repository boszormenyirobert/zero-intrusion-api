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
        $userIntegritySecretObject = $this->identityRepository->findOneBy(['publicId' => $user['publicId']]);
        
        // This is the secret which is responsible to data-integrity and data-decryption
        // The user-credential encrypted by the credential_secret, which is deleted from the database after NFC Card created
        
        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($userIntegritySecretObject);
        $userIntegritySecret = $decrypted->getSecret();

        // Decrypt the user secret by the general database key
        $dbPrivateId = $this->sodiumService->sodiumDecrypt($decrypted->getPrivateId(), $userIntegritySecret);
        $requestPrivateId = $this->sodiumService->sodiumDecrypt($user['privateId'], $userIntegritySecret);
        
        if (\strcmp($requestPrivateId, $dbPrivateId) === 0) {
            $this->logger->critical('PrivateId is valid');
            
            return new ValidationDTO(true, $userIntegritySecret);
        }

        $this->logger->critical('Unvalid PrivateId');
        return new ValidationDTO(false);
    }
}
