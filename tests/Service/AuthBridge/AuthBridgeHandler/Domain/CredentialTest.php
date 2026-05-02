<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler\Domain;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Credential;
use App\Service\Crypters\CrypterDatabaseLoginService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CredentialTest extends TestCase
{
    public function testGetUserCredentialsByDomainProcessIdMapsDecryptedCredentials(): void
    {
        $encryptedBridge = (new AuthBridge())
            ->setDomainProcessId('process-123')
            ->setProcessState(true)
            ->setPublicId('public-123');

        $decryptedBridge = (new AuthBridge())
            ->setApplications(json_encode([
                ['decrypted' => '{"userName":"john.doe"}', 'description' => 'Primary account'],
                ['decrypted' => '{"userName":"jane.doe"}', 'description' => 'Secondary account'],
            ], JSON_THROW_ON_ERROR));

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['domainProcessId' => 'process-123'], ['createdAt' => 'DESC'])
            ->willReturn([$encryptedBridge]);

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->with($encryptedBridge, 'applications')
            ->willReturn($decryptedBridge);

        $service = new Credential(
            $repository,
            $this->createMock(LoggerInterface::class),
            $crypter,
            $this->createMock(LoginDatabaseService::class),
        );

        $result = $service->getUserCredentialsByDomainProcessId('process-123');

        self::assertCount(2, $result);
        self::assertSame('{"userName":"john.doe"}', $result[0]->getCredential());
        self::assertSame('Primary account', $result[0]->getDescription());
        self::assertSame('public-123', $result[0]->getUserPublicId());
        self::assertSame('{"userName":"jane.doe"}', $result[1]->getCredential());
        self::assertSame('Secondary account', $result[1]->getDescription());
        self::assertSame('public-123', $result[1]->getUserPublicId());
    }

    public function testGetUserCredentialsByDomainProcessIdSkipsEntriesWithoutHandyValidation(): void
    {
        $pendingBridge = (new AuthBridge())
            ->setDomainProcessId('process-123')
            ->setProcessState(false)
            ->setPublicId('public-123');

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->method('findBy')
            ->willReturn([$pendingBridge]);

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter->expects(self::never())->method('decryptFromDatabase');

        $service = new Credential(
            $repository,
            $this->createMock(LoggerInterface::class),
            $crypter,
            $this->createMock(LoginDatabaseService::class),
        );

        self::assertSame([], $service->getUserCredentialsByDomainProcessId('process-123'));
    }
}
