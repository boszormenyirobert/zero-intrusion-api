<?php

namespace App\Service\AuthBridge\AuthBridgeHandler;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AuthBridgeRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use App\Service\AuthBridge\AuthBridgeHandler\ValidationHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Credential as ApplicationCredential;
use App\Service\AuthBridge\DTO\ValidationDTO;
use App\Service\Crypters\CrypterDatabaseService;
use App\Entity\AuthBridge;

class AuthBridgeHandler
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private ValidationHandler $validationHandler,
        private Encryptor $encryptor,
        private ApplicationCredential $applicationCredential,
        private CrypterDatabaseService $crypterDatabaseService        
    ) {}

    public function persistDecryptedUserData(array $user): bool
    {
        // Validate the extension request by user privateId.
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);
        if ($validation->getValid()) {
            if($user['type'] === 'domain-login'){
                $this->encryptor->setDecryptedValuesForDomain($user);
            } else if($user['type'] === 'secure'){
                $this->encryptor->setDecryptedUserIdentity($user);
            }            
            else {
                $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
            }
        }

        return $validation->getValid();
    }

    public function persistOneTouchUserData(array $user): bool
    {
        // Validate the extension request by user privateId.
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if ($validation->getValid()) {
            return $user['type'] === 'domain-login'
                ? $this->encryptor->setDecryptedValuesForDomain($user)
                : $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
        }

        return $validation->getValid();
    }    
    

    // Deprecated fs, use getDecryptedUserDataToMobileRequest instead
    public function getDecryptedUserData(array $user): bool
    {
        // Validate the extension request by user privateId
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if ($validation->getValid()) {
            return $user['type'] === 'domain-login'
                ? $this->encryptor->getDecryptedCredentials($user, $validation->getUserSecret())
                : $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
        }

        return $validation->getValid();
    }

    public function getDecryptedUserDataToMobileRequest(array $user): array
    {
        // Validate the extension request by user privateId
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if ($validation->getValid()) {
            return $user['type'] === 'domain-login'
                ? $this->encryptor->getDecryptedCredentials($user)
                : $this->applicationCredential->setDecryptedValuesForApplication($user);
        }

        return [];
    }    

    public function persistDecryptedUserDataForWeb(array $user): ?array
    {
        $response = new ValidationDTO(false);
        
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if ($validation->getValid()) {
            return $this->encryptor->findDecryptedCredentialForWeb($user, $validation->getUserSecret());
        }

        return null;
    }    

    public function updateProcessState(string $processKey, string $processId): bool
    {
        $process = $this->authBridgeRepository->findOneBy([$processKey => $processId]);

        if ($process) {
            $process->setProcessState(true);
            $this->entityManager->persist($process);
            $this->entityManager->flush();
            return true;
        }
        return false;
    }

    public function saveUserCredentialInAuthBridge($userCredential, $registrationProcessId){
        $authBridge = $this->authBridgeRepository->findOneBy(['registrationProcessId' => $registrationProcessId]);

        $iv = $authBridge->getIv();
        $this->logger->critical('IV exist: ' . $iv);
        $encryptedCredential = $this->crypterDatabaseService->enrcyptUserCredential($userCredential, $iv);

        $authBridge->setUserCredential($encryptedCredential['encryptedCredential']);    
        
        $this->entityManager->persist($authBridge);
        $this->entityManager->flush();

        return true;
    }

    public function getUserCredentialFromAuthBridge($processId){
        $authBridge = $this->authBridgeRepository->findOneBy(['registrationProcessId' => $processId]);

        if (!$authBridge) {
            $this->logger->error("No AuthBridge entry found for processId: {$processId}");
            return null;
        }

        $clear =  $this->crypterDatabaseService->decryptUserCredential($authBridge->getUserCredential(), $authBridge->getIv());
        $this->logger->critical('Decrypted user credential: ' . $clear);
        return $clear;
    }
}
