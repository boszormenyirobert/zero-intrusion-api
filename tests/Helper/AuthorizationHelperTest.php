<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\AuthorizationHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AuthorizationHelperTest extends TestCase
{
    public function testGetAuthHeaderUsesConfiguredKeysAndCurrentIv(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('info');

        $helper = new AuthorizationHelper('client-key', 'secret-key', $logger);
        $encryptedPayload = 'encrypted-value';
        $iv = $helper->getIvBase64();

        $header = $helper->getAuthHeader($encryptedPayload);

        self::assertMatchesRegularExpression('/^HMAC client-key:[a-f0-9]{64}:\d+$/', $header);

        [, $value] = explode(' ', $header, 2);
        [$apiKey, $signature] = explode(':', $value, 3);

        self::assertSame('client-key', $apiKey);
        self::assertSame(hash_hmac('sha256', $encryptedPayload . '|' . $iv, 'secret-key'), $signature);
    }

    public function testBuildResponseCreatesExpectedHeaderAndJsonBody(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('info');

        $helper = new AuthorizationHelper('client-key', 'secret-key', $logger);
        $response = $helper->buildResponse('HMAC client-key:signature:123', 'encrypted-value', 'iv-value');

        self::assertSame(
            [
                'Content-Type' => 'application/json',
                'X-Auth' => 'HMAC client-key:signature:123',
            ],
            $response['headers']
        );

        self::assertSame(
            [
                'corporateIdentity' => 'encrypted-value',
                'iv' => 'iv-value',
            ],
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testGetIvBase64ReturnsBase64EncodedSixteenByteIv(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('info');

        $helper = new AuthorizationHelper('client-key', 'secret-key', $logger);
        $iv = $helper->getIvBase64();

        self::assertNotFalse(base64_decode($iv, true));
        self::assertSame(16, strlen((string) base64_decode($iv, true)));
    }
}
