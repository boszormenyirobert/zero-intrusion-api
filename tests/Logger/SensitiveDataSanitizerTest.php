<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\SensitiveDataSanitizer;
use PHPUnit\Framework\TestCase;

final class SensitiveDataSanitizerTest extends TestCase
{
    public function testSanitizeArrayRedactsSensitiveScalarValues(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $sanitized = $sanitizer->sanitizeArray([
            'email' => 'user@example.com',
            'public_id' => 'pub_123456789',
            'secret' => 'top-secret-value',
            'process' => 'user_login',
        ]);

        self::assertStringStartsWith('[redacted:email hash=', $sanitized['email']);
        self::assertStringStartsWith('[redacted:public_id hash=', $sanitized['public_id']);
        self::assertStringStartsWith('[redacted:secret hash=', $sanitized['secret']);
        self::assertSame('user_login', $sanitized['process']);
    }

    public function testSanitizeArraySummarizesStructuredPayloads(): void
    {
        $sanitizer = new SensitiveDataSanitizer();

        $sanitized = $sanitizer->sanitizeArray([
            'response' => '{"privateId":"pid_1","secret":"abc","status":"ok"}',
            'raw_content' => [
                'corporateKey' => 'corp-key',
                'message' => 'ok',
            ],
        ]);

        self::assertStringContainsString('[redacted:response json hash=', $sanitized['response']);
        self::assertStringContainsString('keys=privateId,secret,status', $sanitized['response']);
        self::assertStringContainsString('[redacted:raw_content array hash=', $sanitized['raw_content']);
        self::assertStringContainsString('keys=corporateKey,message', $sanitized['raw_content']);
    }
}
