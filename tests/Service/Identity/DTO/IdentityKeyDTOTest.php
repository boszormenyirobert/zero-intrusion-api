<?php

declare(strict_types=1);

namespace App\Tests\Service\Identity\DTO;

use App\Service\Identity\DTO\IdentityKeyDTO;
use PHPUnit\Framework\TestCase;

final class IdentityKeyDTOTest extends TestCase
{
    public function testToArrayIncludesGeneratedDefaults(): void
    {
        $dto = new IdentityKeyDTO('public-1', 'private-1', 'secret-1', 'credential-secret-1', 'nfc-1');

        self::assertSame([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
            'credentialSecret' => 'credential-secret-1',
            'nfcEncryptionKey' => 'nfc-1',
            'email' => '--not-define-registration-process-one',
            'phone' => '--not-define-registration-process-one',
        ], $dto->toArray());

        self::assertSame([
            'privateSecret' => [
                'publicId' => 'public-1',
                'privateId' => 'private-1',
                'secret' => 'secret-1',
                'credentialSecret' => 'credential-secret-1',
                'email' => '--not-define-registration-process-one',
                'phone' => '--not-define-registration-process-one',
            ],
        ], $dto->toIdentityArray());
    }

    public function testMutatorsReturnSelfAndUpdateIds(): void
    {
        $dto = new IdentityKeyDTO('public-1', 'private-1', 'secret-1', 'credential-secret-1', 'nfc-1');

        self::assertSame($dto, $dto->setPrivateId('private-2'));
        self::assertSame($dto, $dto->setNfcEncryptionKey('nfc-2'));
        self::assertSame('private-2', $dto->getPrivateId());
        self::assertSame('nfc-2', $dto->getNfcEncryptionKey());
    }
}