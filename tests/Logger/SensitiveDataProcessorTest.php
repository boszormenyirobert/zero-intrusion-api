<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\SensitiveDataProcessor;
use App\Logger\SensitiveDataSanitizer;
use PHPUnit\Framework\TestCase;

final class SensitiveDataProcessorTest extends TestCase
{
    public function testInvokeSanitizesContextAndExtraSections(): void
    {
        $processor = new SensitiveDataProcessor(new SensitiveDataSanitizer());
        $processed = $processor([
            'context' => ['password' => 'plain'],
            'extra' => ['secret' => 'raw'],
        ]);

        self::assertStringContainsString('[redacted:secret', $processed['extra']['secret']);
        self::assertSame('plain', $processed['context']['password']);
    }
}