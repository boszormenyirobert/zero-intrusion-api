<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Identity;

use App\DTO\Device\Identity\RecoverySettingsRequestDTO;
use App\Service\Device\Identity\RecoverySettingsService;
use App\Service\Identity\IdentityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RecoverySettingsServiceTest extends TestCase
{
    public function testHandleUpdatesIdentityAndReturnsSuccess(): void
    {
        $request = new RecoverySettingsRequestDTO(
            'public-1',
            'private-1',
            'user@example.test',
            '+3612345678',
            true,
            'token-1',
        );

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('updateIdentityRecoverySettings')
            ->with($request->toArray());

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info')
            ->with('Processing recovery settings update.', ['publicId' => 'public-1']);

        $service = new RecoverySettingsService($identityService, $logger);
        $result = $service->handle($request);

        self::assertSame(['success' => true], $result);
    }
}
