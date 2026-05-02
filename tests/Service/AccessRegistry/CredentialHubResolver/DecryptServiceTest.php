<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Service\AccessRegistry\CredentialHubResolver\DecryptService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class DecryptServiceTest extends TestCase
{
    public function testGetUserDecryptedPagesReturnsDecryptedDomainEntries(): void
    {
        $service = $this->createService();
        $encryptedPage = $this->createCrypter()->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
        ], 'domain');

        $result = $service->getUserDecryptedPages([$encryptedPage], 'domain');

        self::assertCount(1, $result);
        self::assertInstanceOf(AccessRegistry::class, $result[0]);
        self::assertSame('example.com', $result[0]->getDomain());
        self::assertSame('secret-credential', $result[0]->getUserCredential());
        self::assertSame('Primary account', $result[0]->getDescription());
        self::assertSame('public-1', $result[0]->getPublicId());
    }

    public function testGetUserDecryptedPagesReturnsDecryptedApplicationEntries(): void
    {
        $service = $this->createService();
        $encryptedPage = $this->createCrypter()->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'application' => 'mail',
            'userCredential' => 'secret-credential',
            'description' => 'Primary app',
        ], 'application');

        $result = $service->getUserDecryptedPages([$encryptedPage], 'application');

        self::assertCount(1, $result);
        self::assertInstanceOf(AccessRegistry::class, $result[0]);
        self::assertSame('mail', $result[0]->getApplication());
        self::assertSame('secret-credential', $result[0]->getUserCredential());
        self::assertSame('Primary app', $result[0]->getDescription());
    }

    public function testGetUserEncryptedDecryptedPageCollectionPairsEncryptedAndDecryptedPages(): void
    {
        $service = $this->createService();
        $encryptedPage = $this->createCrypter()->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
        ], 'domain');

        $result = $service->getUserEncryptedDecryptedPageCollection([$encryptedPage]);

        self::assertCount(1, $result);
        self::assertSame($encryptedPage, $result[0]['encrypted']);
        self::assertInstanceOf(AccessRegistry::class, $result[0]['decrypted']);
        self::assertSame('example.com', $result[0]['decrypted']->getDomain());
        self::assertSame('secret-credential', $result[0]['decrypted']->getUserCredential());
    }

    public function testGetUserDecryptedPagesRejectsUnknownType(): void
    {
        $service = $this->createService();
        $encryptedPage = $this->createCrypter()->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
        ], 'domain');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid type');

        $service->getUserDecryptedPages([$encryptedPage], 'unknown');
    }

    private function createService(): DecryptService
    {
        return new DecryptService($this->createCrypter());
    }

    private function createCrypter(): CrypterDatabaseAccessRegistryService
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->with('DATABASE_HASH_SECRET')
            ->willReturn('0123456789abcdef0123456789abcdef');

        return new CrypterDatabaseAccessRegistryService(
            $params,
            $this->createMock(LoggerInterface::class),
        );
    }
}
