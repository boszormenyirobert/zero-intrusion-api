<?php

declare(strict_types=1);

namespace App\Tests\Service\Firebase;

use App\Service\Firebase\FirebaseHttpClientAdapter;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class FirebaseHttpClientAdapterTest extends TestCase
{
    public function testPostFormReturnsResponseBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())->method('getContents')->willReturn('{"access_token":"token"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($stream);

        $client = $this->createMock(Client::class);
        $client
            ->expects(self::once())
            ->method('post')
            ->with('https://oauth.example.test/token', self::callback(static function (array $options): bool {
                return $options['form_params']['assertion'] === 'jwt-token'
                    && $options['headers']['Content-Type'] === 'application/x-www-form-urlencoded'
                    && $options['verify'] === '/path/to/cacert.pem';
            }))
            ->willReturn($response);

        $adapter = new FirebaseHttpClientAdapter($client);

        self::assertSame(
            '{"access_token":"token"}',
            $adapter->postForm('https://oauth.example.test/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => 'jwt-token',
            ], [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ], '/path/to/cacert.pem')
        );
    }

    public function testPostJsonReturnsResponseBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())->method('getContents')->willReturn('{"name":"projects/demo/messages/1"}');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($stream);

        $client = $this->createMock(Client::class);
        $client
            ->expects(self::once())
            ->method('post')
            ->with('https://fcm.googleapis.com/v1/projects/demo/messages:send', self::callback(static function (array $options): bool {
                return $options['body'] === '{"message":true}'
                    && $options['headers']['Authorization'] === 'Bearer token'
                    && $options['headers']['Content-Type'] === 'application/json'
                    && $options['verify'] === '/path/to/cacert.pem';
            }))
            ->willReturn($response);

        $adapter = new FirebaseHttpClientAdapter($client);

        self::assertSame(
            '{"name":"projects/demo/messages/1"}',
            $adapter->postJson('https://fcm.googleapis.com/v1/projects/demo/messages:send', '{"message":true}', [
                'Authorization' => 'Bearer token',
                'Content-Type' => 'application/json',
            ], '/path/to/cacert.pem')
        );
    }
}
