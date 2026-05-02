<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Nfc;

use App\DTO\Device\Nfc\NfcDecryptRequestDTO;
use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\Crypters\SodiumService;
use App\Service\Device\Nfc\NfcDecryptService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;

final class NfcDecryptServiceTest extends TestCase
{
    public function testHandleDecryptsCardPayload(): void
    {
        $request = new NfcDecryptRequestDTO('public-1', 'corp-1', 'encrypted-payload');
        $identity = (new Identity())
            ->setPublicId('public-1')
            ->setPrivateId('private-1')
            ->setSecret('secret-1')
            ->setCredentialSecret('credential-1')
            ->setEmail('user@example.test')
            ->setPhone('+3612345678')
            ->setIv(base64_encode(random_bytes(16)))
            ->setNfcEncryptionKey('nfc-key');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn($identity);

        $sodiumService = $this->createMock(SodiumService::class);
        $sodiumService
            ->expects(self::once())
            ->method('sodiumDecrypt')
            ->with('encrypted-payload', 'nfc-key')
            ->willReturn('{"puID":"public-1"}');

        $service = new NfcDecryptService($identityRepository, $sodiumService, new JsonPayloadDecoder());

        self::assertSame(['puID' => 'public-1'], $service->handle($request));
    }
}
