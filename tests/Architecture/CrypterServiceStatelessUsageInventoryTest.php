<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\EventListener\HmacDesktopValidationListener;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Service\Crypters\CrypterService;
use App\Service\Shared\AuthorizedEncryptedResponseFactory;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;

final class CrypterServiceStatelessUsageInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const CONSUMER_CLASSES = [
        RequestService::class,
        AuthorizedEncryptedResponseFactory::class,
        HmacMobileValidationListener::class,
        HmacExtensionValidationListener::class,
        HmacDesktopValidationListener::class,
    ];

    public function testCrypterServiceExposesStatelessEntryPoints(): void
    {
        $source = $this->classSource(CrypterService::class);

        self::assertStringContainsString('public function encrypt(', $source);
        self::assertStringContainsString('public function decrypt(', $source);
    }

    public function testSelectedConsumersDoNotUseMutableSetDataWorkflow(): void
    {
        foreach (self::CONSUMER_CLASSES as $className) {
            $source = $this->classSource($className);

            self::assertStringNotContainsString('->setData(', $source, $className . ' should use stateless CrypterService methods.');
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