<?php

declare(strict_types=1);

namespace App\Tests\Service\QrService;

use App\DTO\QR\QrInterface;
use App\Service\QrService\QrService;
use PHPUnit\Framework\TestCase;

final class QrServiceTest extends TestCase
{
    public function testGetQrCodeReturnsNonEmptyBase64PngPayload(): void
    {
        $dto = new class () implements QrInterface {
            public string $type = 'vault-read';
            public string $processId = 'process-1';
        };

        $encoded = (new QrService())->getQrCode($dto);
        $decoded = base64_decode($encoded, true);

        self::assertNotFalse($decoded);
        self::assertStringStartsWith("\x89PNG", $decoded);
    }
}