<?php

declare(strict_types=1);

namespace App\Tests\Service\Restore;

use App\Entity\Restore;
use App\Exception\EntityNotFoundException;
use App\Exception\MissingKeyException;
use App\Repository\RestoreRepository;
use App\Service\Mailer\MailerService;
use App\Service\Restore\Database\CrypterDatabaseRestoreService;
use App\Service\Restore\Database\RestoreDatabaseService;
use App\Service\Restore\RestoreService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Helper\UtilityHelper;

final class RestoreServiceTest extends TestCase
{
    public function testRecoveryNotificationSendsEmailAndPersistsEncryptedRestore(): void
    {
        $mailerService = $this->createMock(MailerService::class);
        $mailerService
            ->expects(self::once())
            ->method('sendEmail')
            ->with('user@example.test', self::isType('string'));

        $restoreDatabaseService = $this->createMock(RestoreDatabaseService::class);
        $restoreDatabaseService
            ->expects(self::once())
            ->method('addRestore')
            ->with(self::callback(static function (Restore $restore): bool {
                return $restore->getHash() !== null
                    && $restore->getPin() === 123456
                    && $restore->getPublicId() === 'public-1'
                    && $restore->getPrivateId() !== 'private-1'
                    && $restore->getSecret() !== 'secret-1'
                    && $restore->getIv() !== null;
            }));

        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['VONAGE_API_KEY', 'vonage-key'],
                ['VONAGE_API_SECRET', 'vonage-secret'],
                ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
            ]);

        $crypter = new CrypterDatabaseRestoreService($params);

        $service = new RestoreService(
            $this->createMock(LoggerInterface::class),
            $mailerService,
            new UtilityHelper(),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $this->createMock(RestoreRepository::class),
            $crypter,
            $restoreDatabaseService,
        );

        $recoveryData = new class () {
            public function getEmail(): string
            {
                return 'user@example.test';
            }

            public function getPublicId(): string
            {
                return 'public-1';
            }

            public function getPrivateId(): string
            {
                return 'private-1';
            }

            public function getSecret(): string
            {
                return 'secret-1';
            }
        };

        self::assertSame([], $service->recoveryNotification($recoveryData));
    }

    public function testReplaceValidationRejectsMissingFields(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
        ]);

        $service = new RestoreService(
            $this->createMock(LoggerInterface::class),
            $this->createMock(MailerService::class),
            new UtilityHelper(),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $this->createMock(RestoreRepository::class),
            new CrypterDatabaseRestoreService($params),
            $this->createMock(RestoreDatabaseService::class),
        );

        $this->expectException(MissingKeyException::class);
        $this->expectExceptionMessage('Missing required fields: pin or replaceHash');

        $service->replaceValidation([]);
    }

    public function testReplaceValidationRejectsMissingRestoreRecord(): void
    {
        $repository = $this->createMock(RestoreRepository::class);
        $repository->expects(self::once())->method('findOneBy')->willReturn(null);

        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
        ]);

        $service = new RestoreService(
            $this->createMock(LoggerInterface::class),
            $this->createMock(MailerService::class),
            new UtilityHelper(),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $repository,
            new CrypterDatabaseRestoreService($params),
            $this->createMock(RestoreDatabaseService::class),
        );

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('Replace device not found');

        $service->replaceValidation([
            'restorePin' => [
                'data' => ['pin' => 123456],
                'replaceHash' => 'hash-1',
            ],
        ]);
    }

    public function testReplaceValidationReturnsDecryptedRestoreIdentifiers(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
        ]);
        $crypter = new CrypterDatabaseRestoreService($params);

        $decryptedRestore = (new Restore())
            ->setPublicId('public-1')
            ->setPin(123456)
            ->setHash('hash-1')
            ->setPrivateId('private-1')
            ->setSecret('secret-1');
        $encryptedRestore = $crypter->encyptSourceData($decryptedRestore);

        $repository = $this->createMock(RestoreRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['pin' => 123456, 'hash' => 'hash-1'])
            ->willReturn($encryptedRestore);

        $service = new RestoreService(
            $this->createMock(LoggerInterface::class),
            $this->createMock(MailerService::class),
            new UtilityHelper(),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $repository,
            $crypter,
            $this->createMock(RestoreDatabaseService::class),
        );

        self::assertSame([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
        ], $service->replaceValidation([
            'restorePin' => [
                'data' => ['pin' => 123456],
                'replaceHash' => 'hash-1',
            ],
        ]));
    }
}