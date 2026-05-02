<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Service\Hmac\ListenerHmacPolicy;
use PHPUnit\Framework\TestCase;

final class ListenerHmacPolicyInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const LISTENER_CLASSES = [
        HmacMobileValidationListener::class,
        HmacExtensionValidationListener::class,
    ];

    public function testPoolHmacListenersUseSharedListenerPolicy(): void
    {
        foreach (self::LISTENER_CLASSES as $className) {
            $source = $this->classSource($className);

            self::assertStringContainsString(ListenerHmacPolicy::class, $source, $className . ' should depend on the shared listener HMAC policy.');
            self::assertStringNotContainsString('private function isHmacValid', $source, $className . ' should not inline pool HMAC validation.');
            self::assertStringNotContainsString('hash_hmac(', $source, $className . ' should not calculate pool HMAC inline.');
        }
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