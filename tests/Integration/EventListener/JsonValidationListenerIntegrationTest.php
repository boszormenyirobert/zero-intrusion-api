<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class JsonValidationListenerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testRequireJsonRouteRejectsInvalidContentTypeWithUniformPayload(): void
    {
        $client = static::createClient();

        $client->request('POST', '/_test/validation/require-json', server: ['CONTENT_TYPE' => 'text/plain'], content: '{"ok":true}');

        self::assertResponseStatusCodeSame(415);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid Content-Type, expected application/json',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRequireJsonRouteRejectsInvalidJsonWithUniformPayload(): void
    {
        $client = static::createClient();

        $client->request('POST', '/_test/validation/require-json', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid JSON payload',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}