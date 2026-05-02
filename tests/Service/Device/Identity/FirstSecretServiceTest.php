<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Identity;

use App\Service\Device\Identity\FirstSecretService;
use App\Service\Identity\DTO\IdentityKeyDTO;
use App\Service\Identity\IdentityService;
use PHPUnit\Framework\TestCase;

final class FirstSecretServiceTest extends TestCase
{
    public function testHandleReturnsIdentityArray(): void
    {
        $keyDto = new IdentityKeyDTO('public-1', 'private-1', 'secret-1', 'credential-1', 'nfc-1');

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('getKey')
            ->willReturn($keyDto);

        $service = new FirstSecretService($identityService);

        self::assertSame($keyDto->toIdentityArray(), $service->handle());
    }
}
