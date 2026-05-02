<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class MonologProcessorConfigTest extends TestCase
{
    public function testRequestIdProcessorIsRegisteredAsMonologProcessor(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/services.yaml');

        self::assertContains('monolog.processor', $config['services']['App\\Logger\\RequestIdProcessor']['tags'] ?? []);
    }

    public function testSensitiveDataProcessorIsRegisteredAsMonologProcessor(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/services.yaml');

        self::assertContains('monolog.processor', $config['services']['App\\Logger\\SensitiveDataProcessor']['tags'] ?? []);
    }
}
