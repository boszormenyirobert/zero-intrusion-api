<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventSubscriber;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExceptionSubscriberIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testUnexpectedExceptionsReturnSanitizedJsonResponse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_test/exception/runtime');

        self::assertResponseStatusCodeSame(500);
        self::assertStringStartsWith('application/json', (string) $client->getResponse()->headers->get('content-type'));
        self::assertSame(
            ['success' => false, 'error' => 'Internal Server Error'],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testClientHttpExceptionsPreserveSafeClientMessage(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_test/exception/not-found');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            ['success' => false, 'error' => 'Route not found'],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testDomainExceptionsStillUseMappedApiErrorPayload(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_test/exception/entity-not-found');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(
            ['success' => false, 'error' => 'Entity not found'],
            json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );
    }
}