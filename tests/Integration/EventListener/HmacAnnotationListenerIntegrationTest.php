<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener;

use App\Kernel;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HmacAnnotationListenerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testRequireHmacRouteRejectsInvalidJsonWithUniformPayload(): void
    {
        $client = static::createClient();

        $client->request('POST', '/_test/validation/require-hmac', server: ['CONTENT_TYPE' => 'application/json'], content: '{invalid');

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid JSON body',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRequireHmacRouteRejectsMissingFieldsWithUniformPayload(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/_test/validation/require-hmac',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['iv' => 'only-iv'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Missing required fields',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRequireHmacRouteAllowsValidPayloadWhenValidatorApproves(): void
    {
        $client = static::createClient();

        $validator = $this->createMock(HmacValidator::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);

        static::getContainer()->set(HmacValidator::class, $validator);

        $client->request(
            'POST',
            '/_test/validation/require-hmac',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'success' => true,
            'message' => 'hmac-ok',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}