<?php

declare(strict_types=1);

namespace App\Service\Firebase;

use App\Repository\IdentityRepository;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\Payload\JsonPayloadDecoder;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class FirebaseService
{
    private ?FirebaseMessagePayloadFactory $firebaseMessagePayloadFactory = null;
    private ?FirebaseHttpClientAdapter $firebaseHttpClientAdapter = null;
    private ?FirebaseConfig $firebaseConfig = null;
    private ?FirebaseTokenProvider $firebaseTokenProvider = null;

    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly IdentityRepository $identityRepository,
        private readonly CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
        private readonly LoggerInterface $logger,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    // Find the user by publicId, retrieve all stored FCM tokens,
    // decrypt each token using the database's default encryption (IV + key),
    // and send a push notification to every associated device.
    public function manageFcm($publicId, $title, $body, $qrData): bool
    {
        $identityEncrypted = $this->identityRepository->findOneBy(['publicId' => $publicId]);
        if (!$identityEncrypted) {
            return false;
        }

        $fcmTokens = $identityEncrypted->getFcmToken() ?? [];

        if (!is_array($fcmTokens) || count($fcmTokens) === 0) {
            return false;
        }

        $delivered = 0;

        foreach ($fcmTokens as $index => $token) {
            $fcmToken = $this->crypterDatabaseIdentityService->decryptData($token, base64_decode($identityEncrypted->getIv()));
            if ($this->sendFcmMessage($fcmToken, $title, $body, $qrData)) {
                $delivered++;
            }
        }

        if ($delivered === 0) {
            $this->logger->warning('FCM notification was not delivered to any token.', [
                'publicId' => $publicId,
                'tokenCount' => count($fcmTokens),
                'title' => $title,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Sends a push notification to a specific device using Firebase Cloud Messaging (FCM).
     * Steps:
     * 1. Generates a JWT for Firebase authentication via getJwt().
     * 2. Exchanges the JWT for an access token with getAccessToken().
     * 3. Calls sendFCM() to deliver the notification to the given deviceToken with the provided title, body, and QR data.
     */
    public function sendFcmMessage($deviceToken, $title, $body, $qrData): bool
    {
        $accessToken = $this->tokenProvider()->getAccessToken();
        if (!$accessToken) {
            $this->logger->error('FCM send aborted because access token retrieval failed.', [
                'maskedToken' => $this->maskToken($deviceToken),
                'title' => $title,
            ]);
            return false;
        }

        return $this->sendFCM($deviceToken, $title, $body, $accessToken, $qrData) !== null;
    }

    /**
     * Generates a signed JWT (JSON Web Token) for Firebase service account authentication.
     * Steps:
     * 1. Creates a JWT header with algorithm RS256.
     * 2. Creates a claim containing issuer (client_email), scope (Firebase messaging), audience (token URI), issued-at and expiry timestamps.
     * 3. Signs the header and claim using the service account's private key.
     * 4. Returns the complete JWT for authentication with Firebase APIs.
     */    
    private function getJwt(): ?string
    {
        return $this->tokenProvider()->createJwt();
    }

    /**
     * Exchanges a signed JWT for a Firebase OAuth2 access token.
     * Steps:
     * 1. Sends a POST request to Firebase's token endpoint using the JWT as the OAuth assertion.
     * 2. Uses 'urn:ietf:params:oauth:grant-type:jwt-bearer' to request a service-account access token.
     * 3. Parses the JSON response and extracts the 'access_token' if present.
     * 4. Logs errors if the token request fails or the response is invalid.
     * 5. Returns the access token for authenticated FCM requests.
     */    
    private function getAccessToken($jwt): ?string
    {
        if (!$jwt) {
            return null;
        }

        return $this->tokenProvider()->getAccessTokenFromJwt((string) $jwt);
    }

    /**
     * Sends a Firebase Cloud Messaging (FCM) push notification to a specific device.
     * Steps:
     * 1. Builds the FCM message payload, including title, body, and custom data (qrData).
     * 2. Sends a POST request to the FCM v1 API using the provided OAuth access token.
     * 3. Handles and logs Firebase responses or errors, including HTTP status and error body.
     * 4. Returns the response body on success.
     */    
    private function sendFCM($deviceToken, $title, $body, $accessToken, $qrData): ?string
    {
        $project_id = $this->config()->getProjectId();

        $url = "https://fcm.googleapis.com/v1/projects/$project_id/messages:send";

        $message = $this->createMessagePayload((string) $deviceToken, (string) $title, (string) $body, $qrData);

        try {
            $this->logger->info('Outgoing HTTP request.', [
                'channel' => 'firebase',
                'operation' => 'send_message',
                'method' => 'POST',
                'url' => $url,
                'payload' => $message,
                'maskedToken' => $this->maskToken($deviceToken),
            ]);

            $response = $this->httpClientAdapter()->postJson(
                $url,
                (string) json_encode($message),
                [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                $this->config()->getCaCertPath(),
            );

            $this->logger->info('Outgoing HTTP response.', [
                'channel' => 'firebase',
                'operation' => 'send_message',
                'method' => 'POST',
                'url' => $url,
                'statusCode' => $response['statusCode'],
                'responseBody' => $response['body'],
                'maskedToken' => $this->maskToken($deviceToken),
            ]);

            return $response['body'];

        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            $context = [
                'channel' => 'firebase',
                'operation' => 'send_message',
                'method' => 'POST',
                'projectId' => $project_id,
                'maskedToken' => $this->maskToken($deviceToken),
                'title' => $title,
                'requestUrl' => $url,
                'payload' => $message,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ];

            if ($exception->hasResponse()) {
                $resp = $exception->getResponse();
                $responseBody = $resp->getBody()->getContents();
                $decodedBody = $this->jsonPayloadDecoder->decodeArray($responseBody) ?? [];

                $context['statusCode'] = $resp->getStatusCode();
                $context['responseBody'] = $responseBody;
                $context['firebaseStatus'] = $decodedBody['error']['status'] ?? null;
                $context['firebaseMessage'] = $decodedBody['error']['message'] ?? null;
                $context['firebaseCode'] = $decodedBody['error']['code'] ?? null;
                $context['firebaseErrorCode'] = $decodedBody['error']['details'][0]['errorCode'] ?? null;
            }

            $this->logger->error('Outgoing HTTP request failed.', $context);
        }

        return null;
    }

    private function maskToken(?string $token): string
    {
        if (!$token) {
            return 'empty-token';
        }

        $length = strlen($token);
        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, 6) . '...' . substr($token, -4);
    }

    /**
     * @return array<string, mixed>
     */
    private function createMessagePayload(string $deviceToken, string $title, string $body, mixed $qrData): array
    {
        return $this->messagePayloadFactory()->create($deviceToken, $title, $body, $qrData);
    }

    private function config(): FirebaseConfig
    {
        return $this->firebaseConfig ??= new FirebaseConfig(
            (string) $this->params->get('FIREBASE_PROJECT_ID'),
            (string) $this->params->get('FIREBASE_CLIENT_EMAIL'),
            (string) $this->params->get('FIREBASE_PRIVATE_KEY'),
            (string) $this->params->get('FIREBASE_TOKEN_URI'),
            (string) $this->params->get('FIREBASE_CACERT_PATH'),
        );
    }

    private function messagePayloadFactory(): FirebaseMessagePayloadFactory
    {
        return $this->firebaseMessagePayloadFactory ??= new FirebaseMessagePayloadFactory();
    }

    private function httpClientAdapter(): FirebaseHttpClientAdapter
    {
        return $this->firebaseHttpClientAdapter ??= new FirebaseHttpClientAdapter();
    }

    private function tokenProvider(): FirebaseTokenProvider
    {
        return $this->firebaseTokenProvider ??= new FirebaseTokenProvider(
            $this->config(),
            $this->httpClientAdapter(),
            $this->logger,
            $this->jsonPayloadDecoder,
        );
    }
}