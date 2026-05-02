<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Domain\Delete;

use App\Controller\CredentialHub\Domain\Delete\DomainDeleteService;
use PHPUnit\Framework\TestCase;

final class DomainDeleteServiceTest extends TestCase
{
    public function testGetQrContentBuildsDeleteDto(): void
    {
        $service = (new \ReflectionClass(DomainDeleteService::class))->newInstanceWithoutConstructor();
        $dto = $service->getQrContent('example.test', 'domain-delete', 'extension', 'target-1', 'auth-1', 'process-1');

        self::assertSame('auth-1', $dto->xExtensionAuthOne);
        self::assertSame('example.test', $dto->domain);
        self::assertSame('domain-delete', $dto->type);
        self::assertSame('extension', $dto->source);
        self::assertSame('target-1', $dto->targetId);
        self::assertSame('process-1', $dto->removeProcessId);
    }
}