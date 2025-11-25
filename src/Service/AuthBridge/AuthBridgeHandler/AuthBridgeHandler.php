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

class AuthBridgeHandler
{
    public function __construct(
        private AuthBridgeRepository $authBridgeRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private SerializerInterface $serializer,
        private ValidationHandler $validationHandler,
        private Encryptor $encryptor,
        private ApplicationCredential $applicationCredential
    ) {}

    public function persistDecryptedUserData(array $user): bool
    {
        // Validate the extension request by user privateId
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if ($validation->getValid()) {
            return $user['type'] === 'domain-login'
                ? $this->encryptor->setDecryptedValuesForDomain($user, $validation->getUserSecret())
                : $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
        }

        return $validation->getValid();
    }

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
                ? $this->encryptor->getDecryptedCredentials($user, $validation)
                : $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
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
}
