<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\EventListener\HmacDesktopValidationListener;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Service\Hmac\ListenerPayloadResolver;
use PHPUnit\Framework\TestCase;

final class ListenerPayloadResolverInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const LISTENER_CLASSES = [
        HmacMobileValidationListener::class,
        HmacExtensionValidationListener::class,
        HmacDesktopValidationListener::class,
    ];

    public function testHmacListenersUseSharedPayloadResolver(): void
    {
        foreach (self::LISTENER_CLASSES as $className) {
            $source = $this->classSource($className);

            self::assertStringContainsString(ListenerPayloadResolver::class, $source, $className . ' should depend on the shared listener payload resolver.');
            self::assertStringNotContainsString('decodeArray($request->getContent())', $source, $className . ' should not decode request content inline.');
            self::assertStringNotContainsString("decrypt((string) \$payload['zeroIntrusionProyApi'])", $source, $className . ' should not decrypt listener payloads inline.');
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