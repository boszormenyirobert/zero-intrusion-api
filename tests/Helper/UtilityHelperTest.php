<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Helper\UtilityHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

final class UtilityHelperTest extends TestCase
{
    public function testGenerateIdentityReturnsExpectedKeySet(): void
    {
        $identity = UtilityHelper::generateIdentity();

        self::assertSame(['corporate_id', 'corporate_id_key', 'corporate_id_secret'], array_keys($identity));
        self::assertStringStartsWith('cid_', $identity['corporate_id']);
        self::assertStringStartsWith('ckey_', $identity['corporate_id_key']);
        self::assertStringStartsWith('csec_', $identity['corporate_id_secret']);
    }

    public function testBuildPathNormalizesSlashes(): void
    {
        self::assertSame(
            'https://example.test/api/account/all',
            UtilityHelper::buildPath('https://example.test/', '/api/', 'account/all')
        );
    }

    public function testValidateJsonFormatReturnsDecodedPayload(): void
    {
        $request = Request::create('/api/account/all', 'POST', [], [], [], [], json_encode(['key' => 'value'], JSON_THROW_ON_ERROR));

        self::assertSame(['key' => 'value'], UtilityHelper::validateJsonFormat($request));
    }

    public function testValidateJsonFormatRejectsInvalidJsonPayload(): void
    {
        $request = Request::create('/api/account/all', 'POST', [], [], [], [], '{invalid');

        self::assertSame(['error' => 'Invalid JSON payload'], UtilityHelper::validateJsonFormat($request));
    }

    public function testValidateAuthHeaderFormatParsesHmacHeader(): void
    {
        $matches = UtilityHelper::validateAuthHeaderFormat('HMAC client-key:signature');

        self::assertSame('HMAC client-key:signature', $matches[0]);
        self::assertSame('client-key', $matches[1]);
        self::assertSame('signature', $matches[2]);
    }

    public function testValidateAuthHeaderFormatParsesTimestampedHmacHeader(): void
    {
        $matches = UtilityHelper::validateAuthHeaderFormat('HMAC client-key:signature:123');

        self::assertSame('HMAC client-key:signature:123', $matches[0]);
        self::assertSame('client-key', $matches[1]);
        self::assertSame('signature', $matches[2]);
        self::assertSame('123', $matches[3]);
    }

    public function testCompareExpectationsReturnsSuccessForMatchingSignature(): void
    {
        $encryptedData = 'encrypted-payload';
        $iv = 'iv-value';
        $signature = hash_hmac('sha256', $encryptedData . '|' . $iv, 'secret-key');
        $params = $this->createParameterBag();

        self::assertSame(
            ['error' => false],
            UtilityHelper::compareExpectations(['', 'client-key', $signature], $params, $encryptedData, $iv)
        );
    }

    public function testCompareExpectationsRejectsUnknownApiKey(): void
    {
        $params = $this->createParameterBag();

        self::assertSame(
            ['error' => 'Unknown API key'],
            UtilityHelper::compareExpectations(['', 'other-key', 'signature'], $params, 'encrypted-payload', 'iv-value')
        );
    }

    private function createParameterBag(): ParameterBagInterface&MockObject
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['SERVICE_API_KEY', 'client-key'],
                ['SERVICE_API_SECRET', 'secret-key'],
            ]);

        return $params;
    }
}
