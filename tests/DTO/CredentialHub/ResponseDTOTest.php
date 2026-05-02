<?php

declare(strict_types=1);

namespace App\Tests\DTO\CredentialHub;

use App\DTO\CredentialHub\ResponseDTO;
use App\Entity\AuthBridge;
use PHPUnit\Framework\TestCase;

final class ResponseDTOTest extends TestCase
{
    public function testStateArraysContainCurrentDtoState(): void
    {
        $authBridge = new AuthBridge();
        $dto = new ResponseDTO(true, 'validated', false, $authBridge);

        $dto->setCredential('credential-1');
        $dto->setDescription('description-1');
        $dto->setUserPublicId('public-1');

        self::assertTrue($dto->isProcess());
        self::assertSame('validated', $dto->getValidation());
        self::assertFalse($dto->isProcessCheck());
        self::assertSame($authBridge, $dto->getData());
        self::assertSame('credential-1', $dto->getCredential());
        self::assertSame('description-1', $dto->getDescription());
        self::assertSame('public-1', $dto->getUserPublicId());

        self::assertSame([
            'process' => true,
            'validation' => 'validated',
            'process_check' => false,
            'credential' => 'credential-1',
            'description' => 'description-1',
            'publicId' => 'public-1',
        ], $dto->toDomainStateArray());

        self::assertSame($dto->toDomainStateArray(), $dto->toResponseArray());

        self::assertSame([
            'process' => true,
            'validation' => 'validated',
            'process_check' => false,
        ], $dto->toStateArray());

        self::assertSame($dto->toStateArray(), $dto->toVaultStateArray());
    }

    public function testMutatorsUpdateCoreFlagsAndData(): void
    {
        $dto = new ResponseDTO(false, false, true);
        $authBridge = new AuthBridge();

        $dto->setProcess(true);
        $dto->setValidation('ok');
        $dto->setProcessCheck(false);
        $dto->setData($authBridge);

        self::assertTrue($dto->isProcess());
        self::assertSame('ok', $dto->getValidation());
        self::assertFalse($dto->isProcessCheck());
        self::assertSame($authBridge, $dto->getData());
    }
}