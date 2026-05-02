<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Log\LoggerInterface;

final class AuthorizationHelper
{
    private const CONTENT_TYPE = 'application/json';
    private const HMAC_PREFIX = 'HMAC';
    private const HEADER_CONTENT_TYPE = 'Content-Type';
    private const HEADER_X_AUTH = 'X-Auth';

    private string $ivBase64;

    public function __construct(
        private readonly string $clientApiKey,
        private readonly string $secretApiKey,
        private readonly LoggerInterface $logger,
    ) {
        $this->setIvBase64();
    }

    public function getAuthHeader(string $encryptedDataValue): string
    {
        $ivBase64 = $this->getIvBase64();
        $authorization = sprintf(
            '%s %s:%s:%d',
            self::HMAC_PREFIX,
            $this->clientApiKey,
            $this->buildSignature($encryptedDataValue, $ivBase64),
            time()
        );

        $this->logger->info('AuthorizationHelper getAuthHeader generated HMAC header.', [
            'encrypted_length' => strlen($encryptedDataValue),
            'iv_present' => !empty($ivBase64),
        ]);

        return $authorization;
    }

    public function getIvBase64(): string
    {
        return $this->ivBase64;
    }

    /**
     * @return array{headers: array<string, string>, body: string}
     */
    public function buildResponse(string $authorization, string $encryptedData, string $ivBase64): array
    {
        $header = [
            self::HEADER_CONTENT_TYPE => self::CONTENT_TYPE,
            self::HEADER_X_AUTH => $authorization,
        ];

        $payload = [
            'corporateIdentity' => $encryptedData,
            'iv' => $ivBase64,
        ];

        $this->logger->info('AuthorizationHelper buildResponse prepared payload.', [
            'header_keys' => array_keys($header),
            'encrypted_length' => strlen($encryptedData),
            'iv_present' => !empty($ivBase64),
        ]);

        return [
            'headers' => $header,
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ];
    }

    private function setIvBase64(): void
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->ivBase64 = base64_encode($iv);

        $this->logger->info('AuthorizationHelper setIvBase64 generated IV.', [
            'iv_present' => $this->ivBase64 !== '',
            'iv_length' => strlen($this->ivBase64),
        ]);
    }

    private function buildSignature(string $encryptedDataValue, string $ivBase64): string
    {
        return hash_hmac('sha256', sprintf('%s|%s', $encryptedDataValue, $ivBase64), $this->secretApiKey);
    }
}
