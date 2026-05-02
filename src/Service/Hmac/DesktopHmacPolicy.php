<?php

declare(strict_types=1);

namespace App\Service\Hmac;

use App\Entity\CorporateIdentity;

final class DesktopHmacPolicy
{
    private const MAX_TIME_DRIFT_SECONDS = 300;

    public function validateSignature(string $receivedSignature, string $corporateId, int $timestamp, CorporateIdentity $corporate): bool
    {
        $expectedSecret = (string) $corporate->getCorporateIdSecret();
        $expectedCorporateIdKey = (string) $corporate->getCorporateIdKey();

        $message = $expectedCorporateIdKey . '|' . $timestamp;
        $expectedSignature = strtolower(hash_hmac('sha256', $message, $expectedSecret));

        return hash_equals($expectedSignature, strtolower($receivedSignature));
    }

    public function isTimestampWithinWindow(int $timestamp): bool
    {
        return abs(time() - $timestamp) <= self::MAX_TIME_DRIFT_SECONDS;
    }
}