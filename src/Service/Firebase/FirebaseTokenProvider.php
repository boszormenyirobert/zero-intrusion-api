<?php

declare(strict_types=1);

namespace App\Service\Firebase;

use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use App\Service\Payload\JsonPayloadDecoder;

class FirebaseTokenProvider
{
    public function __construct(
        private readonly FirebaseConfig $config,
        private readonly FirebaseHttpClientAdapter $httpClientAdapter,
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function createJwt(): ?string
    {
        $header = $this->encodeJwtSegment([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]);

        $now = time();
        $claim = $this->encodeJwtSegment([
            'iss' => $this->config->getClientEmail(),
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $this->config->getTokenUri(),
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $signature = $this->signJwtPayload($header, $claim, $this->config->getPrivateKey());

        if ($signature === null) {
            $this->logger->error('Firebase JWT signing failed.', [
                'operation' => 'firebase_access_token',
            ]);

            return null;
        }

        return $header . '.' . $claim . '.' . base64_encode($signature);
    }

    public function getAccessToken(): ?string
    {
        $jwt = $this->createJwt();
        if ($jwt === null) {
            return null;
        }

        return $this->getAccessTokenFromJwt($jwt);
    }

    public function getAccessTokenFromJwt(string $jwt): ?string
    {
        $data = [];
        $requestPayload = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertionPreview' => $this->maskValue($jwt),
        ];

        try {
            $url = $this->config->getTokenUri();

            $this->logger->info('Outgoing HTTP request.', [
                'channel' => 'firebase',
                'operation' => 'access_token',
                'method' => 'POST',
                'url' => $url,
                'payload' => $requestPayload,
            ]);

            $response = $this->httpClientAdapter->postForm(
                $url,
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                $this->config->getCaCertPath(),
            );

            $this->logger->info('Outgoing HTTP response.', [
                'channel' => 'firebase',
                'operation' => 'access_token',
                'method' => 'POST',
                'url' => $url,
                'statusCode' => $response['statusCode'],
                'responseBody' => $response['body'],
            ]);

            $data = $this->jsonPayloadDecoder->decodeArray($response['body']) ?? [];

            if (empty($data['access_token'])) {
                $this->logger->error('Firebase access token response is missing access_token.', [
                    'url' => $url,
                    'responseBody' => $response['body'],
                ]);
            }
        } catch (RequestException $exception) {
            $context = [
                'channel' => 'firebase',
                'operation' => 'access_token',
                'method' => 'POST',
                'url' => $this->config->getTokenUri(),
                'payload' => $requestPayload,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ];

            if ($exception->hasResponse()) {
                $context['statusCode'] = $exception->getResponse()->getStatusCode();
                $context['responseBody'] = $exception->getResponse()->getBody()->getContents();
            }

            $this->logger->error('Outgoing HTTP request failed.', $context);
        }

        return $data['access_token'] ?? null;
    }

    private function maskValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 6) . '...' . substr($value, -4);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodeJwtSegment(array $payload): string
    {
        return base64_encode(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function signJwtPayload(string $header, string $claim, string $privateKey): ?string
    {
        $signature = null;
        $success = openssl_sign($header . '.' . $claim, $signature, $privateKey, 'SHA256');

        if ($success !== true || !is_string($signature)) {
            return null;
        }

        return $signature;
    }
}
