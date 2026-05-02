<?php

declare(strict_types=1);

namespace App\Service\Hmac;

use App\Entity\AuthBridge;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ListenerHmacPolicy
{
    private const MAX_TIME_DRIFT_SECONDS = 12;

    public function __construct(
        private readonly ParameterBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validatePoolHeader(?string $authHeader, AuthBridge $process, string $algorithm): bool
    {
        $secret = (string) $this->params->get('EXTENSION_REGISTRATION_POOL_SECRET');
        $message = (string) $this->params->get('EXTENSION_REGISTRATION_POOL_MESSAGE');
        $hmacValue = $this->extractHmacValue($authHeader);

        if (!is_string($hmacValue)) {
            $this->logger->error('Invalid HMAC header format.');

            return false;
        }

        $createdAt = $process->getCreatedAt()?->getTimestamp();
        if ($createdAt === null) {
            return false;
        }

        if (abs($createdAt - time()) > self::MAX_TIME_DRIFT_SECONDS) {
            $this->logger->error('Time difference too large.');

            return false;
        }

        $expected = hash_hmac($algorithm, $message . '|' . $createdAt, $secret);
        if (!hash_equals($expected, $hmacValue)) {
            $this->logger->critical('HMAC mismatch.');

            return false;
        }

        return true;
    }

    private function extractHmacValue(?string $header): string|false
    {
        if (!$header) {
            return false;
        }

        if (!str_starts_with($header, 'HMAC ')) {
            return false;
        }

        $parts = explode(' ', $header, 2);

        return trim($parts[1] ?? '');
    }
}