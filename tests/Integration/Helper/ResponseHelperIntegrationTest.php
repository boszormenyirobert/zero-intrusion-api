<?php

declare(strict_types=1);

namespace App\Tests\Integration\Helper;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ResponseHelperIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSuccessResponseEndpointUsesSuccessfulContract(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_test/response-helper/success');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'process' => true,
            'validation' => false,
            'process_check' => false,
            'success' => true,
            'payload' => ['id' => 'public-1'],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSuccessResponseEndpointDoesNotExposeFalseSuccessFlag(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_test/response-helper/success-false-flag');

        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['success']);
    }

    public function testProcessAndExceptionEndpointsKeepCurrentErrorContracts(): void
    {
        $client = static::createClient();

        $client->request('GET', '/_test/response-helper/process');
        self::assertResponseStatusCodeSame(200);
        self::assertSame([
            'success' => false,
            'error' => 'process-error',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));

        $client->request('GET', '/_test/response-helper/exception');
        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid payload or missing required data.',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}