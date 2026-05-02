<?php

declare(strict_types=1);

namespace App\Tests\DTO\QR;

use App\DTO\QR\CredentialHubIdentityDTO;
use PHPUnit\Framework\TestCase;

final class CredentialHubIdentityDTOTest extends TestCase
{
    public function testToProcessArrayReturnsDomainPayloadForKnownProcessKey(): void
    {
        $dto = new CredentialHubIdentityDTO();
        $dto->setValidCommunication(['mobile' => true]);
        $dto->setCreatedAt('2026-01-01T00:00:00+00:00');
        $dto->setXExtensionAuthOne('auth-1');
        $dto->setXExtensionAuthTwo('auth-2');
        $dto->setSecret('secret');
        $dto->setIv('iv');
        $dto->setDomainProcessId('process-1');
        $dto->setQrCode('qr-code');

        self::assertSame($dto->toDomainProcessArray(), $dto->toProcessArray('domainProcessId'));
    }

    public function testToProcessArrayRejectsUnknownProcessKey(): void
    {
        $dto = new CredentialHubIdentityDTO();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported process key: invalidProcessId');

        $dto->toProcessArray('invalidProcessId');
    }
}