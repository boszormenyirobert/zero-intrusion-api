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

    public function checkExtensionRequestValidation(array $user): ValidationDTO
    {
        $userSecretObject = $this->identityRepository->findOneBy(['publicId' => $user['publicId']]);
        $decrypted = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($userSecretObject);
        $userSecret = $decrypted->getSecret();

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
