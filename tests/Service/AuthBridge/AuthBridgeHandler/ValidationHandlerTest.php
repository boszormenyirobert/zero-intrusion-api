<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler;

use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\AuthBridge\AuthBridgeHandler\ValidationHandler;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ValidationHandlerTest extends TestCase
{
    public function testCheckExtensionRequestValidationReturnsValidDtoForMatchingPrivateId(): void
    {
        $identity = (new Identity())->setPublicId('public-1');
        $decryptedIdentity = (new Identity())
            ->setSecret('user-secret')
            ->setPrivateId('db-private-id');

        $repository = $this->createMock(IdentityRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn($identity);

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('decryptFromDatabaseidentity')
            ->with($identity)
            ->willReturn($decryptedIdentity);

        $sodium = $this->createMock(SodiumService::class);
        $sodium
            ->expects(self::exactly(2))
            ->method('sodiumDecrypt')
            ->willReturnMap([
                ['db-private-id', 'user-secret', 'resolved-private-id'],
                ['request-private-id', 'user-secret', 'resolved-private-id'],
            ]);

        $service = new ValidationHandler(
            $repository,
            $crypter,
            $this->createMock(LoggerInterface::class),
            $sodium,
        );

        $result = $service->checkExtensionRequestValidation([
            'publicId' => 'public-1',
            'privateId' => 'request-private-id',
        ]);

        self::assertTrue($result->getValid());
        self::assertSame('user-secret', $result->getUserSecret());
    }

    public function testCheckExtensionRequestValidationReturnsInvalidDtoForMismatchedPrivateId(): void
    {
        $identity = (new Identity())->setPublicId('public-2');
        $decryptedIdentity = (new Identity())
            ->setSecret('user-secret')
            ->setPrivateId('db-private-id');

        $repository = $this->createMock(IdentityRepository::class);
        $repository->method('findOneBy')->willReturn($identity);

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter->method('decryptFromDatabaseidentity')->willReturn($decryptedIdentity);

        $sodium = $this->createMock(SodiumService::class);
        $sodium
            ->method('sodiumDecrypt')
            ->willReturnMap([
                ['db-private-id', 'user-secret', 'resolved-db-private-id'],
                ['request-private-id', 'user-secret', 'resolved-request-private-id'],
            ]);

        $service = new ValidationHandler(
            $repository,
            $crypter,
            $this->createMock(LoggerInterface::class),
            $sodium,
        );

        $result = $service->checkExtensionRequestValidation([
            'publicId' => 'public-2',
            'privateId' => 'request-private-id',
        ]);

        self::assertFalse($result->getValid());
        self::assertNull($result->getUserSecret());
        self::assertSame(
            ['valid' => false, 'error' => 'Unvalid PrivateId : Validation failed.'],
            $result->toArrayUnValid()
        );
    }
}
