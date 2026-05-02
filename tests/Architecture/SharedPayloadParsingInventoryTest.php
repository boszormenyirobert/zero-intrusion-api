<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Service\CredentialHub\Shared\SharedRegistrationNewService;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;

final class SharedPayloadParsingInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const SERVICE_CLASSES = [
        SharedRegistrationNewService::class,
        SharedRegistrationNewToEncryptService::class,
    ];

    public function testSharedRegistrationServicesUseSharedJsonPayloadDecoder(): void
    {
        foreach (self::SERVICE_CLASSES as $className) {
            $source = $this->classSource($className);

            self::assertStringContainsString(JsonPayloadDecoder::class, $source, $className . ' should depend on the shared JSON payload decoder.');
            self::assertStringNotContainsString('private function decodePayload', $source, $className . ' should not duplicate local payload decoding helpers.');
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