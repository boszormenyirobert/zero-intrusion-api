<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler;

use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use App\Repository\AuthBridgeRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use App\Service\AuthBridge\AuthBridgeHandler\ValidationHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Credential as ApplicationCredential;
use App\Service\AuthBridge\DTO\ValidationDTO;
use App\Service\Crypters\CrypterDatabaseService;
use App\Entity\AuthBridge;
use App\Service\Shared\ProcessTypeNormalizer;

class AuthBridgeHandler
{
    public function __construct(
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly SerializerInterface $serializer,
        private readonly ValidationHandler $validationHandler,
        private readonly Encryptor $encryptor,
        private readonly ApplicationCredential $applicationCredential,
        private readonly CrypterDatabaseService $crypterDatabaseService,
        private readonly ProcessTypeNormalizer $processTypeNormalizer,
    ) {}

    public function persistDecryptedUserData(array $user): bool
    {
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);
        if ($validation->getValid() === false) {
            return false;
        }

        if ($this->processTypeNormalizer->isDomainLoginType($user['type'] ?? null)) {
            return $this->encryptor->setDecryptedValuesForDomain($user);
        }

        return match ($user['type'] ?? null) {
            'secure' => $this->encryptor->setDecryptedUserIdentity($user),
            'applications' => $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret()),
            default => false,
        };
    }

    public function persistOneTouchUserData(array $user): bool
    {
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if (!$validation->getValid()) {
            return false;
        }

        return $this->processTypeNormalizer->isDomainLoginType($user['type'] ?? null)
            ? $this->encryptor->setDecryptedValuesForDomain($user)
            : $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
    }
    

    // Deprecated fs, use getDecryptedUserDataToMobileRequest instead
    public function getDecryptedUserData(array $user): bool
    {
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if (!$validation->getValid()) {
            return false;
        }

        return $this->processTypeNormalizer->isDomainLoginType($user['type'] ?? null)
            ? $this->encryptor->getDecryptedCredentials($user, $validation->getUserSecret())
            : $this->applicationCredential->setDecryptedValuesForApplication($user, $validation->getUserSecret());
    }

    public function getDecryptedUserDataToMobileRequest(array $user): array
    {
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if (!$validation->getValid()) {
            return [];
        }

        return $this->processTypeNormalizer->isDomainLoginType($user['type'] ?? null)
            ? $this->encryptor->getDecryptedCredentials($user)
            : $this->applicationCredential->setDecryptedValuesForApplication($user);
    }

    public function persistDecryptedUserDataForWeb(array $user): ?array
    {
        $validation = $this->validationHandler->checkExtensionRequestValidation($user);

        if (!$validation->getValid()) {
            return null;
        }

        return $this->encryptor->findDecryptedCredentialForWeb($user, $validation->getUserSecret());
    }

    public function updateProcessState(string $processKey, string $processId): bool
    {
        $process = $this->authBridgeRepository->findOneBy([$processKey => $processId]);

        if (!$process instanceof AuthBridge) {
            return false;
        }

        $process->setProcessState(true);
        $this->entityManager->persist($process);
        $this->entityManager->flush();

        return true;
    }

    public function saveUserCredentialInAuthBridge(mixed $userCredential, string $registrationProcessId): bool
    {
        $authBridge = $this->authBridgeRepository->findOneBy(['registrationProcessId' => $registrationProcessId]);
        if (!$authBridge instanceof AuthBridge) {
            return false;
        }

        $iv = $authBridge->getIv();
        $this->logger->critical('IV exist: ' . $iv);
        $encryptedCredential = $this->crypterDatabaseService->encryptUserCredentialOrFail($userCredential, (string) $iv);

        $authBridge->setUserCredential($encryptedCredential);    

        $this->entityManager->persist($authBridge);
        $this->entityManager->flush();

        return true;
    }

    public function getUserCredentialFromAuthBridge(string $processId): ?string
    {
        $authBridge = $this->authBridgeRepository->findOneBy(['registrationProcessId' => $processId]);

        if (!$authBridge instanceof AuthBridge) {
            $this->logger->error("No AuthBridge entry found for processId: {$processId}");

            return null;
        }

        $clear = $this->crypterDatabaseService->decryptUserCredentialOrFail((string) $authBridge->getUserCredential(), (string) $authBridge->getIv());

        try {
            $encodedCredential = json_encode($clear, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON encoding failed', 0, $exception);
        }

        $this->logger->critical('Decrypted user credential: ' . $encodedCredential);

        return $encodedCredential;
    }
}
