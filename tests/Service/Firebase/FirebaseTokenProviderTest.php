<?php

declare(strict_types=1);

namespace App\Tests\Service\Firebase;

use App\Service\Firebase\FirebaseConfig;
use App\Service\Firebase\FirebaseHttpClientAdapter;
use App\Service\Firebase\FirebaseTokenProvider;
use App\Service\Payload\JsonPayloadDecoder;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;

final class FirebaseTokenProviderTest extends TestCase
{
    public function testCreateJwtReturnsNullWhenPrivateKeyIsInvalid(): void
    {
        $provider = new FirebaseTokenProvider(
            new FirebaseConfig(
                'demo-project',
                'firebase@example.test',
                'invalid-private-key',
                'https://oauth.example.test/token',
                '/path/to/cacert.pem',
            ),
            new FirebaseHttpClientAdapter(),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_WARNING && str_contains($message, 'openssl_sign():');
        });

        try {
            self::assertNull($provider->createJwt());
        } finally {
            restore_error_handler();
        }
    }

    public function testGetAccessTokenReturnsDecodedTokenFromAdapterResponse(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())->method('getContents')->willReturn('{"access_token":"token-123"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($stream);

        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('post')->willReturn($response);

        $provider = new FirebaseTokenProvider(
            new FirebaseConfig(
                'demo-project',
                'firebase@example.test',
                '-----BEGIN PRIVATE KEY-----\nkey\n-----END PRIVATE KEY-----',
                'https://oauth.example.test/token',
                '/path/to/cacert.pem',
            ),
            new FirebaseHttpClientAdapter($client),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        self::assertSame('token-123', $provider->getAccessTokenFromJwt('jwt-token'));
    }
}
