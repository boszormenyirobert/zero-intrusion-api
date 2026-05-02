<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\EventListener\HmacDesktopValidationListener;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Service\Device\Nfc\NfcDecryptService;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;

final class JsonBoundaryDecoderInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const CLASS_NAMES = [
        RequestService::class,
        NfcDecryptService::class,
        HmacDesktopValidationListener::class,
        HmacExtensionValidationListener::class,
        HmacMobileValidationListener::class,
    ];

    public function testJsonBoundaryClassesUseSharedDecoder(): void
    {
        foreach (self::CLASS_NAMES as $className) {
            $source = $this->classSource($className);

            self::assertStringContainsString(JsonPayloadDecoder::class, $source, $className . ' should depend on the shared JSON payload decoder.');
            self::assertStringNotContainsString('private function decodeJson', $source, $className . ' should not duplicate JSON decoding helpers.');
            self::assertStringNotContainsString('private function decodePayload', $source, $className . ' should not duplicate payload decoding helpers.');
            self::assertStringNotContainsString('json_decode(', $source, $className . ' should not parse JSON inline.');
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