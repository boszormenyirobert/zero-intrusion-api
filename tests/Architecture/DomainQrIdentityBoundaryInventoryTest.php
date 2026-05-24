<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Controller\CredentialHub\Domain\Delete\DomainDeleteService;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\DTO\CredentialHub\Domain\Read\ExtensionCredentialRequestDTO;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityService;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityService;
use PHPUnit\Framework\TestCase;

final class DomainQrIdentityBoundaryInventoryTest extends TestCase
{
    public function testDomainQrIdentityDtosExposeExplicitFieldsOnly(): void
    {
        self::assertSame(
            ['domain', 'userPublicId'],
            $this->publicPropertyNames(ExtensionCredentialRequestDTO::class),
            'Domain read QR identity DTO should expose only explicit boundary fields.'
        );

        self::assertSame(
            ['domain', 'type', 'source', 'targetId', 'userPublicId'],
            $this->publicPropertyNames(DomainDeleteQrIdentityRequestDTO::class),
            'Domain delete QR identity DTO should expose only explicit boundary fields.'
        );
    }

    public function testDomainQrIdentityServicesDoNotDependOnRawPayloadProperties(): void
    {
        self::assertStringNotContainsString('->payload', $this->classSource(DomainReadQrIdentityService::class));
        self::assertStringNotContainsString('->payload', $this->classSource(DomainDeleteQrIdentityService::class));
    }

    public function testDomainDeleteServiceUsesExplicitQrContentArguments(): void
    {
        $method = new \ReflectionMethod(DomainDeleteService::class, 'getQrContent');
        $parameterNames = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        );

        self::assertSame(
            ['domain', 'type', 'source', 'targetId', 'mobilXExtensionAuth', 'processId'],
            $parameterNames,
            'Domain delete QR content builder should accept explicit boundary arguments.'
        );
    }

    /** @return list<string> */
    private function publicPropertyNames(string $className): array
    {
        $reflection = new \ReflectionClass($className);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        return array_map(static fn (\ReflectionProperty $property): string => $property->getName(), $properties);
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