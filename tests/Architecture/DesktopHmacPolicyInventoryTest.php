<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\EventListener\HmacDesktopValidationListener;
use App\Service\Hmac\DesktopHmacPolicy;
use PHPUnit\Framework\TestCase;

final class DesktopHmacPolicyInventoryTest extends TestCase
{
    public function testDesktopListenerUsesSharedDesktopPolicy(): void
    {
        $source = $this->classSource(HmacDesktopValidationListener::class);

        self::assertStringContainsString(DesktopHmacPolicy::class, $source, 'Desktop listener should depend on the shared desktop HMAC policy.');
        self::assertStringNotContainsString('hash_hmac(', $source, 'Desktop listener should not calculate HMAC signatures inline.');
    }

    private function classSource(string $className): string
    {
        $reflection = new \ReflectionClass($className);
        $fileName = $reflection->getFileName();

        self::assertIsString($fileName);

        $contents = file_get_contents($fileName);
        self::assertIsString($contents);

        return $contents;
    }
}