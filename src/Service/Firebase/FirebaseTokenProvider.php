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
            $this->logger->critical('JWT aláírás sikertelen');

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

        try {
            $body = $this->httpClientAdapter->postForm(
                $this->config->getTokenUri(),
                [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ],
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                $this->config->getCaCertPath(),
            );

            $data = $this->jsonPayloadDecoder->decodeArray($body) ?? [];

            if (!empty($data['access_token'])) {
                $this->logger->info('Firebase access token successfully received.');
            } else {
                $this->logger->error('Firebase response did not contain an access_token.', [
                    'responseBody' => $body,
                ]);
            }
        } catch (RequestException $e) {
            $context = [
                'message' => $e->getMessage(),
            ];

            if ($e->hasResponse()) {
                $context['statusCode'] = $e->getResponse()->getStatusCode();
                $context['responseBody'] = $e->getResponse()->getBody()->getContents();
            }

            $this->logger->critical('Firebase access token request failed.', $context);
        }

        return $data['access_token'] ?? null;
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
