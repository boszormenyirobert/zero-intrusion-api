<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler;

use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use Psr\Log\LoggerInterface;
use App\Repository\IdentityRepository;
use App\Service\AuthBridge\DTO\ValidationDTO;

class ValidationHandler
{
    private const VALID_PRIVATE_ID_LOG = 'PrivateId is valid';
    private const INVALID_PRIVATE_ID_LOG = 'Unvalid PrivateId';

    public function __construct(
        private readonly IdentityRepository $identityRepository,
        private readonly CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private readonly LoggerInterface $logger,
        private readonly SodiumService $sodiumService,
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
        $userIntegritySecret = (string) $decrypted->getSecret();

        // Decrypt the user secret by the general database key
        $dbPrivateId = $this->resolvePrivateId((string) $decrypted->getPrivateId(), $userIntegritySecret);
        $requestPrivateId = $this->resolvePrivateId((string) $user['privateId'], $userIntegritySecret);
        
        if (\strcmp($requestPrivateId, $dbPrivateId) === 0) {
            $this->logger->critical(self::VALID_PRIVATE_ID_LOG);
            
            return new ValidationDTO(true, $userIntegritySecret);
        }

        $this->logger->critical(self::INVALID_PRIVATE_ID_LOG);
        return new ValidationDTO(false);
    }

    private function resolvePrivateId(string $privateId, string $userIntegritySecret): string
    {
        return (string) $this->sodiumService->sodiumDecrypt($privateId, $userIntegritySecret);
    }
}
