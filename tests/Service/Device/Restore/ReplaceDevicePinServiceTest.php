<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Restore;

use App\DTO\Device\Restore\ReplaceDevicePinRequestDTO;
use App\Service\Device\Restore\ReplaceDevicePinService;
use App\Service\Restore\RestoreService;
use PHPUnit\Framework\TestCase;

final class ReplaceDevicePinServiceTest extends TestCase
{
    public function testHandleDelegatesToRestoreService(): void
    {
        $request = new ReplaceDevicePinRequestDTO([
            'restorePin' => ['replaceHash' => 'hash-1', 'data' => ['pin' => '1234']],
        ]);

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::once())
            ->method('replaceValidation')
            ->with($request->toArray())
            ->willReturn(['publicId' => 'public-1']);

        $service = new ReplaceDevicePinService($restoreService);

        self::assertSame(['publicId' => 'public-1'], $service->handle($request));
    }
}
