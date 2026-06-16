<?php

declare(strict_types=1);

namespace App\Service\Firebase;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

final class FirebaseHttpClientAdapter
{
    public function __construct(
        private readonly Client $client = new Client(),
    ) {
    }

    /**
     * @return array{statusCode: int, body: string}
     */
    public function postForm(string $url, array $formParams, array $headers, string $caCertPath): array
    {
        $response = $this->client->post($url, [
            'form_params' => $formParams,
            'headers' => $headers,
            'verify' => $caCertPath,
        ]);

        return $this->readResponse($response);
    }

    /**
     * @return array{statusCode: int, body: string}
     */
    public function postJson(string $url, string $body, array $headers, string $caCertPath): array
    {
        $response = $this->client->post($url, [
            'body' => $body,
            'headers' => $headers,
            'verify' => $caCertPath,
        ]);

        return $this->readResponse($response);
    }

    /**
     * @return array{statusCode: int, body: string}
     */
    private function readResponse(ResponseInterface $response): array
    {
        return [
            'statusCode' => $response->getStatusCode(),
            'body' => $response->getBody()->getContents(),
        ];
    }
}
