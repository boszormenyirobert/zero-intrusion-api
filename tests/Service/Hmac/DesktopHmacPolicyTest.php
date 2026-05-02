<?php

declare(strict_types=1);

namespace App\Tests\Service\Hmac;

use App\Entity\CorporateIdentity;
use App\Service\Hmac\DesktopHmacPolicy;
use PHPUnit\Framework\TestCase;

final class DesktopHmacPolicyTest extends TestCase
{
    public function testValidateSignatureAcceptsMatchingSignatureWithinTimeWindow(): void
    {
        $policy = new DesktopHmacPolicy();
        $corporate = (new CorporateIdentity())
            ->setCorporateIdKey('desktop-key')
            ->setCorporateIdSecret('desktop-secret');
        $timestamp = time();

        self::assertTrue($policy->validateSignature(
            strtolower(hash_hmac('sha256', 'desktop-key|' . $timestamp, 'desktop-secret')),
            'corp-123',
            $timestamp,
            $corporate,
        ));
    }

    public function testValidateSignatureRejectsMismatchedSignature(): void
    {
        $policy = new DesktopHmacPolicy();
        $corporate = (new CorporateIdentity())
            ->setCorporateIdKey('desktop-key')
            ->setCorporateIdSecret('desktop-secret');

        self::assertFalse($policy->validateSignature('invalid-signature', 'corp-123', time(), $corporate));
    }

    public function testIsTimestampWithinWindowRejectsExpiredTimestamp(): void
    {
        $policy = new DesktopHmacPolicy();

        self::assertFalse($policy->isTimestampWithinWindow(time() - 301));
    }
}