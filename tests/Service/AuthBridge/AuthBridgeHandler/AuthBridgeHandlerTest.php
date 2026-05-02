<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Credential as ApplicationCredential;
use App\Service\AuthBridge\AuthBridgeHandler\AuthBridgeHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\AuthBridge\AuthBridgeHandler\ValidationHandler;
use App\Service\AuthBridge\DTO\ValidationDTO;
use App\Service\Crypters\CrypterDatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class AuthBridgeHandlerTest extends TestCase
{
    public function testPersistDecryptedUserDataReturnsFalseWhenValidationFails(): void
    {
        $validationHandler = $this->createMock(ValidationHandler::class);
        $validationHandler
            ->expects(self::once())
            ->method('checkExtensionRequestValidation')
            ->with(['type' => 'domain-login'])
            ->willReturn(new ValidationDTO(false));

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects(self::never())->method('setDecryptedValuesForDomain');

        $handler = $this->createHandler(validationHandler: $validationHandler, encryptor: $encryptor);

        self::assertFalse($handler->persistDecryptedUserData(['type' => 'domain-login']));
    }

    public function testPersistDecryptedUserDataUsesDomainEncryptorForDomainLogin(): void
    {
        $user = ['type' => 'domain-login', 'publicId' => 'public-1'];
        $validationHandler = $this->createMock(ValidationHandler::class);
        $validationHandler
            ->method('checkExtensionRequestValidation')
            ->with($user)
            ->willReturn(new ValidationDTO(true, 'user-secret'));

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor
            ->expects(self::once())
            ->method('setDecryptedValuesForDomain')
            ->with($user)
            ->willReturn(true);

        $applicationCredential = $this->createMock(ApplicationCredential::class);
        $applicationCredential->expects(self::never())->method('setDecryptedValuesForApplication');

        $handler = $this->createHandler(
            validationHandler: $validationHandler,
            encryptor: $encryptor,
            applicationCredential: $applicationCredential,
        );

        self::assertTrue($handler->persistDecryptedUserData($user));
    }

    public function testPersistDecryptedUserDataUsesApplicationCredentialForNonDomainLogin(): void
    {
        $user = ['type' => 'application-login', 'publicId' => 'public-1'];
        $validationHandler = $this->createMock(ValidationHandler::class);
        $validationHandler
            ->method('checkExtensionRequestValidation')
            ->with($user)
            ->willReturn(new ValidationDTO(true, 'user-secret'));

        $applicationCredential = $this->createMock(ApplicationCredential::class);
        $applicationCredential
            ->expects(self::once())
            ->method('setDecryptedValuesForApplication')
            ->with($user, 'user-secret')
            ->willReturn(true);

        $handler = $this->createHandler(
            validationHandler: $validationHandler,
            applicationCredential: $applicationCredential,
        );

        self::assertTrue($handler->persistDecryptedUserData($user));
    }

    public function testUpdateProcessStatePersistsAndFlushesWhenProcessExists(): void
    {
        $process = new AuthBridge();
        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['registrationProcessId' => 'process-123'])
            ->willReturn($process);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($process);
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler(repository: $repository, entityManager: $entityManager);

        self::assertTrue($handler->updateProcessState('registrationProcessId', 'process-123'));
        self::assertTrue($process->isProcessState());
    }

    public function testSaveAndGetUserCredentialInAuthBridgeUseCrypterService(): void
    {
        $bridge = (new AuthBridge())
            ->setRegistrationProcessId('process-123')
            ->setIv(base64_encode(random_bytes(16)));

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('findOneBy')
            ->with(['registrationProcessId' => 'process-123'])
            ->willReturn($bridge);

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService
            ->expects(self::once())
            ->method('encryptUserCredentialOrFail')
            ->with(['userName' => 'john.doe'], $bridge->getIv())
            ->willReturn('encrypted-value');
        $crypterDatabaseService
            ->expects(self::once())
            ->method('decryptUserCredentialOrFail')
            ->with('encrypted-value', $bridge->getIv())
            ->willReturn(['userName' => 'john.doe']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($bridge);
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler(
            repository: $repository,
            entityManager: $entityManager,
            crypterDatabaseService: $crypterDatabaseService,
        );

        self::assertTrue($handler->saveUserCredentialInAuthBridge(['userName' => 'john.doe'], 'process-123'));
        self::assertSame('{"userName":"john.doe"}', $handler->getUserCredentialFromAuthBridge('process-123'));
    }

    private function createHandler(
        ?AuthBridgeRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
        ?ValidationHandler $validationHandler = null,
        ?Encryptor $encryptor = null,
        ?ApplicationCredential $applicationCredential = null,
        ?CrypterDatabaseService $crypterDatabaseService = null,
    ): AuthBridgeHandler {
        return new AuthBridgeHandler(
            $repository ?? $this->createMock(AuthBridgeRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(SerializerInterface::class),
            $validationHandler ?? $this->createMock(ValidationHandler::class),
            $encryptor ?? $this->createMock(Encryptor::class),
            $applicationCredential ?? $this->createMock(ApplicationCredential::class),
            $crypterDatabaseService ?? $this->createMock(CrypterDatabaseService::class),
        );
    }
}
