<?php

declare(strict_types=1);

namespace App\Tests\DTO\QR;

use App\DTO\QR\OneTouchDTO;
use PHPUnit\Framework\TestCase;

final class OneTouchDTOTest extends TestCase
{
    public function testConstructorSettersAndGettersRoundTrip(): void
    {
        $dto = new OneTouchDTO(
            'process-1',
            'auth-1',
            'registration-domain',
            'extension',
            'public-1',
            'target-1',
        );

        $dto->setValidCommunication(['mobile', 'extension']);
        $dto->setCreatedAt('2026-04-27T20:00:00+00:00');
        $dto->setXExtensionAuthOne('auth-2');
        $dto->setXExtensionAuthTwo('auth-3');
        $dto->setSecret('secret');
        $dto->setIv('iv');
        $dto->setRegistrationProcessId('registration-1');
        $dto->setRemoveProcessId('remove-1');
        $dto->setDomainProcessId('domain-1');
        $dto->setApplicationProcessId('application-1');
        $dto->setQrCode('qr-code');
        $dto->setOneTouchProcessId('process-2');

        self::assertSame(['mobile', 'extension'], $dto->getValidCommunication());
        self::assertSame('2026-04-27T20:00:00+00:00', $dto->getCreatedAt());
        self::assertSame('auth-2', $dto->getXExtensionAuthOne());
        self::assertSame('auth-3', $dto->getXExtensionAuthTwo());
        self::assertSame('secret', $dto->getSecret());
        self::assertSame('iv', $dto->getIv());
        self::assertSame('registration-1', $dto->getRegistrationProcessId());
        self::assertSame('remove-1', $dto->getRemoveProcessId());
        self::assertSame('domain-1', $dto->getDomainProcessId());
        self::assertSame('application-1', $dto->getApplicationProcessId());
        self::assertSame('qr-code', $dto->getQrCode());
        self::assertSame('process-2', $dto->getOneTouchProcessId());
    }

    public function testArrayConversionsExposeExpectedProcessSpecificPayloads(): void
    {
        $dto = new OneTouchDTO('one-touch-1', 'auth-1', 'registration-domain', 'extension', 'public-1', 'target-1');
        $dto->setValidCommunication(['mobile']);
        $dto->setCreatedAt('2026-04-27');
        $dto->setXExtensionAuthTwo('auth-2');
        $dto->setSecret('secret');
        $dto->setIv('iv');
        $dto->setRegistrationProcessId('registration-1');
        $dto->setDomainProcessId('domain-1');
        $dto->setRemoveProcessId('remove-1');
        $dto->setApplicationProcessId('application-1');
        $dto->setQrCode('qr-code');

        self::assertSame([
            'validCommunication' => ['mobile'],
            'createdAt' => '2026-04-27',
            'xExtensionAuthOne' => 'auth-1',
            'xExtensionAuthTwo' => 'auth-2',
            'secret' => 'secret',
            'iv' => 'iv',
            'registrationProcessId' => 'registration-1',
            'qrCode' => 'qr-code',
        ], $dto->toRegistrationProcessArray());

        self::assertSame([
            'validCommunication' => ['mobile'],
            'createdAt' => '2026-04-27',
            'xExtensionAuthOne' => 'auth-1',
            'xExtensionAuthTwo' => 'auth-2',
            'secret' => 'secret',
            'iv' => 'iv',
            'domainProcessId' => 'domain-1',
            'qrCode' => 'qr-code',
        ], $dto->toDomainProcessArray());

        self::assertSame([
            'validCommunication' => ['mobile'],
            'createdAt' => '2026-04-27',
            'xExtensionAuthOne' => 'auth-1',
            'xExtensionAuthTwo' => 'auth-2',
            'secret' => 'secret',
            'iv' => 'iv',
            'removeProcessId' => 'remove-1',
            'qrCode' => 'qr-code',
        ], $dto->toRemoveProcessArray());

        self::assertSame([
            'validCommunication' => ['mobile'],
            'createdAt' => '2026-04-27',
            'xExtensionAuthOne' => 'auth-1',
            'xExtensionAuthTwo' => 'auth-2',
            'secret' => 'secret',
            'iv' => 'iv',
            'applicationProcessId' => 'application-1',
            'qrCode' => 'qr-code',
        ], $dto->toApplicationProcessArray());

        self::assertSame($dto->toApplicationProcessArray(), $dto->toProcessArray('applicationProcessId'));
        self::assertSame([
            'validCommunication' => ['mobile'],
            'createdAt' => '2026-04-27',
            'xExtensionAuthOne' => 'auth-1',
            'xExtensionAuthTwo' => 'auth-2',
            'secret' => 'secret',
            'iv' => 'iv',
            'oneTouchProcessId' => 'one-touch-1',
            'qrCode' => 'qr-code',
        ], $dto->toResponseArray());
    }

    public function testToProcessArrayRejectsUnknownProcessKey(): void
    {
        $dto = new OneTouchDTO('one-touch-1', 'auth-1', 'registration-domain', 'extension', 'public-1', 'target-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported process key: invalidProcessId');

        $dto->toProcessArray('invalidProcessId');
    }
}