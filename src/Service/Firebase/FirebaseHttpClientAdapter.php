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

    public function postForm(string $url, array $formParams, array $headers, string $caCertPath): string
    {
        $response = $this->client->post($url, [
            'form_params' => $formParams,
            'headers' => $headers,
            'verify' => $caCertPath,
        ]);

        return $this->readBody($response);
    }

    public function postJson(string $url, string $body, array $headers, string $caCertPath): string
    {
        $response = $this->client->post($url, [
            'body' => $body,
            'headers' => $headers,
            'verify' => $caCertPath,
        ]);

        return $this->readBody($response);
    }

    private function readBody(ResponseInterface $response): string
    {
        return $response->getBody()->getContents();
    }
}
