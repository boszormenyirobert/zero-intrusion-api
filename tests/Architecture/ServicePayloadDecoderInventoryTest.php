<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use App\Service\Firebase\FirebaseService;
use App\Service\Hmac\HmacValidator;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;

final class ServicePayloadDecoderInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const SERVICE_CLASSES = [
        HmacValidator::class,
        SharedPayloadService::class,
        SharedProcessPoller::class,
        FirebaseService::class,
    ];

    public function testSelectedServicesUseSharedJsonPayloadDecoder(): void
    {
        foreach (self::SERVICE_CLASSES as $className) {
            $source = $this->classSource($className);

            self::assertStringContainsString(JsonPayloadDecoder::class, $source, $className . ' should depend on the shared JSON payload decoder.');
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