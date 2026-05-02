<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler;

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

final class AuthBridgeHandlerAdditionalTest extends TestCase
{
    public function testPersistOneTouchUserDataDelegatesByType(): void
    {
        $validationHandler = $this->createMock(ValidationHandler::class);
        $validationHandler->method('checkExtensionRequestValidation')->willReturn(new ValidationDTO(true, 'user-secret'));

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects(self::once())->method('setDecryptedValuesForDomain')->with(['type' => 'domain-login'])->willReturn(true);

        $applicationCredential = $this->createMock(ApplicationCredential::class);
        $applicationCredential->expects(self::once())->method('setDecryptedValuesForApplication')->with(['type' => 'vault-read'], 'user-secret')->willReturn(true);

        $handler = $this->createHandler($validationHandler, $encryptor, $applicationCredential);

        self::assertTrue($handler->persistOneTouchUserData(['type' => 'domain-login']));
        self::assertTrue($handler->persistOneTouchUserData(['type' => 'vault-read']));
    }

    public function testGetDecryptedUserDataThrowsTypeErrorWhenDomainEncryptorReturnsArray(): void
    {
        $validationHandler = $this->createMock(ValidationHandler::class);
        $validationHandler
            ->method('checkExtensionRequestValidation')
            ->willReturnOnConsecutiveCalls(
                new ValidationDTO(false),
                new ValidationDTO(true, 'user-secret'),
            );

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects(self::once())->method('getDecryptedCredentials')->with(['type' => 'domain-login'], 'user-secret')->willReturn(['credential' => 'secret']);

        $handler = $this->createHandler($validationHandler, $encryptor, $this->createMock(ApplicationCredential::class));

        self::assertFalse($handler->getDecryptedUserData(['type' => 'domain-login']));

        $this->expectException(\TypeError::class);
        $handler->getDecryptedUserData(['type' => 'domain-login']);
    }

    public function testMobileAndWebAccessorsCoverRemainingBranches(): void
    {
        $validationHandler = $this->createMock(ValidationHandler::class);
        $validationHandler
            ->method('checkExtensionRequestValidation')
            ->willReturnOnConsecutiveCalls(
                new ValidationDTO(false),
                new ValidationDTO(true),
                new ValidationDTO(true, 'web-secret'),
                new ValidationDTO(false),
            );

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects(self::once())->method('findDecryptedCredentialForWeb')->with(['type' => 'domain-web'], 'web-secret')->willReturn(['decrypted' => 'web']);

        $applicationCredential = $this->createMock(ApplicationCredential::class);
        $applicationCredential->expects(self::once())->method('setDecryptedValuesForApplication')->with(['type' => 'app-mobile'])->willReturn(true);

        $handler = $this->createHandler($validationHandler, $encryptor, $applicationCredential);

        self::assertSame([], $handler->getDecryptedUserDataToMobileRequest(['type' => 'domain-login-mobile']));

        try {
            $handler->getDecryptedUserDataToMobileRequest(['type' => 'app-mobile']);
            self::fail('Expected TypeError for application mobile request.');
        } catch (\TypeError) {
            self::assertTrue(true);
        }

        self::assertSame(['decrypted' => 'web'], $handler->persistDecryptedUserDataForWeb(['type' => 'domain-web']));
        self::assertNull($handler->persistDecryptedUserDataForWeb(['type' => 'domain-web']));
    }

    public function testMissingAuthBridgeEntriesReturnFalseOrNull(): void
    {
        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::exactly(3))
            ->method('findOneBy')
            ->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('No AuthBridge entry found for processId: process-1');

        $handler = new AuthBridgeHandler(
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $logger,
            $this->createMock(SerializerInterface::class),
            $this->createMock(ValidationHandler::class),
            $this->createMock(Encryptor::class),
            $this->createMock(ApplicationCredential::class),
            $this->createMock(CrypterDatabaseService::class),
        );

        self::assertFalse($handler->updateProcessState('registrationProcessId', 'process-1'));
        self::assertFalse($handler->saveUserCredentialInAuthBridge(['userName' => 'john'], 'process-1'));
        self::assertNull($handler->getUserCredentialFromAuthBridge('process-1'));
    }

    private function createHandler(ValidationHandler $validationHandler, Encryptor $encryptor, ApplicationCredential $applicationCredential): AuthBridgeHandler
    {
        return new AuthBridgeHandler(
            $this->createMock(AuthBridgeRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(SerializerInterface::class),
            $validationHandler,
            $encryptor,
            $applicationCredential,
            $this->createMock(CrypterDatabaseService::class),
        );
    }
}