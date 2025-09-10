<?php

namespace App\Helper;
use Psr\Log\LoggerInterface;

final class AuthorizationHelper
{
    private $secretApiKey = null;
    private $clientApiKey = null;
    private $ivBase64 = null;
    private $logger;

    public function __construct(
        $clientApiKey, 
        $secretApiKey,  
        $logger)
    {
        $this->logger=$logger;
        $this->clientApiKey = $clientApiKey;
        $this->secretApiKey = $secretApiKey;
        $this->setIvBase64();
    }

    public function getAuthHeader($encryptedDataValue)
    {
        $ivBase64 = $this->getIvBase64();
        $message = "$encryptedDataValue|$ivBase64";
        $signature = hash_hmac('sha256', $message, $this->secretApiKey);
        $authorization = 'HMAC ' . $this->clientApiKey . ':' . $signature;

        return $authorization;
    }

    private function setIvBase64()
    {
        $iv = openssl_random_pseudo_bytes(16);
        $this->ivBase64 = base64_encode($iv);
    }

    public function getIvBase64()
    {
        return $this->ivBase64;
    }

    public function buildResponse($authorization, $encryptedData, $ivBase64)
    {
        $header = [
            'Content-Type' => 'application/json',
            'X-Auth' => $authorization
        ];

        $payload = [
            'corporateIdentity' => $encryptedData,
            'iv' => $ivBase64
        ];

        return [
            'headers' => $header,
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR)
        ];
    }
}
