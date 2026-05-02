<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Nfc;

use App\DTO\Device\Nfc\NfcDecryptRequestDTO;
use App\Repository\IdentityRepository;
use App\Service\Crypters\SodiumService;
use App\Service\Device\Nfc\NfcDecryptService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;

final class NfcDecryptServiceAdditionalTest extends TestCase
{
    public function testHandleReturnsEmptyArrayForInvalidJsonPayload(): void
    {
        $request = new NfcDecryptRequestDTO('public-1', 'corp-1', 'encrypted-payload');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn(null);

        $sodiumService = $this->createMock(SodiumService::class);
        $sodiumService
            ->expects(self::once())
            ->method('sodiumDecrypt')
            ->with('encrypted-payload', '')
            ->willReturn('{invalid-json');

        $service = new NfcDecryptService($identityRepository, $sodiumService, new JsonPayloadDecoder());

        self::assertSame([], $service->handle($request));
    }

    public function testHandleReturnsEmptyArrayWhenJsonDecodesToScalar(): void
    {
        $request = new NfcDecryptRequestDTO('public-1', 'corp-1', 'encrypted-payload');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->willReturn(null);

        $sodiumService = $this->createMock(SodiumService::class);
        $sodiumService
            ->expects(self::once())
            ->method('sodiumDecrypt')
            ->with('encrypted-payload', '')
            ->willReturn('"scalar"');

        $service = new NfcDecryptService($identityRepository, $sodiumService, new JsonPayloadDecoder());

        self::assertSame([], $service->handle($request));
    }
}
