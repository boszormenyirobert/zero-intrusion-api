<?php

namespace App\Service\Firebase;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use App\Repository\IdentityRepository;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class FirebaseService
{
        public function __construct(
            private ContainerBagInterface $params,
            private IdentityRepository $identityRepository,
            private CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
            private LoggerInterface $logger
        ) {
        }

    // Find the user by publicId, retrieve all stored FCM tokens,
    // decrypt each token using the database's default encryption (IV + key),
    // and send a push notification to every associated device.
    public function manageFcm($publicId, $title, $body, $qrData)
    {
        $identityEncrypted = $this->identityRepository->findOneBy(['publicId' => $publicId]);
        if($identityEncrypted){
            $fcmTokens = $identityEncrypted->getFcmToken() ?? [];
            $this->logger->info('Preparing FCM delivery.', [
                'publicId' => $publicId,
                'tokenCount' => count($fcmTokens),
                'title' => $title,
            ]);

            foreach ($fcmTokens as $index => $token) {
                $fcmToken = $this->crypterDatabaseIdentityService->decryptData($token, base64_decode($identityEncrypted->getIv()));
                $this->logger->info('Sending FCM to device token.', [
                    'publicId' => $publicId,
                    'title' => $title,
                    'tokenIndex' => $index + 1,
                    'tokenCount' => count($fcmTokens),
                    'maskedToken' => $this->maskToken($fcmToken),
                ]);
                $this->sendFcmMessage($fcmToken, $title, $body, $qrData);
            }
        }
    }

    /**
     * Sends a push notification to a specific device using Firebase Cloud Messaging (FCM).
     * Steps:
     * 1. Generates a JWT for Firebase authentication via getJwt().
     * 2. Exchanges the JWT for an access token with getAccessToken().
     * 3. Calls sendFCM() to deliver the notification to the given deviceToken with the provided title, body, and QR data.
     */
    public function sendFcmMessage($deviceToken, $title, $body, $qrData) {        
        $jwt = $this->getJwt();
        if (!$jwt) {
            $this->logger->critical('FCM send aborted because JWT generation failed.', [
                'maskedToken' => $this->maskToken($deviceToken),
                'title' => $title,
            ]);
            return;
        }

        $accessToken = $this->getAccessToken($jwt);
        if (!$accessToken) {
            $this->logger->critical('FCM send aborted because access token retrieval failed.', [
                'maskedToken' => $this->maskToken($deviceToken),
                'title' => $title,
            ]);
            return;
        }

        $this->sendFCM($deviceToken, $title, $body, $accessToken, $qrData);
    }

    /**
     * Generates a signed JWT (JSON Web Token) for Firebase service account authentication.
     * Steps:
     * 1. Creates a JWT header with algorithm RS256.
     * 2. Creates a claim containing issuer (client_email), scope (Firebase messaging), audience (token URI), issued-at and expiry timestamps.
     * 3. Signs the header and claim using the service account's private key.
     * 4. Returns the complete JWT for authentication with Firebase APIs.
     */    
    private function getJwt() {
        $client_email = $this->params->get('FIREBASE_CLIENT_EMAIL');
        $private_key = $this->params->get('FIREBASE_PRIVATE_KEY');
        $header = base64_encode(json_encode([
            "alg" => "RS256",
            "typ" => "JWT"
        ]));

        $now = time();
        $claim = base64_encode(json_encode([
            "iss" => $client_email,
            "scope" => "https://www.googleapis.com/auth/firebase.messaging",
            "aud" => $this->params->get('FIREBASE_TOKEN_URI'),
            "iat" => $now,
            "exp" => $now + 3600
        ]));


        $success = openssl_sign("$header.$claim", $signature, $private_key, "SHA256");
        if (!$success) {
            $this->logger->critical('JWT aláírás sikertelen');       
            return null;
        } else {
            $this->logger->critical('JWT aláírás sikeres: '. $success);       
        }

       // 2. JWT signing
        openssl_sign("$header.$claim", $signature, $private_key, "SHA256");
        $jwt = "$header.$claim." . base64_encode($signature);

        return $jwt;
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
    private function getAccessToken($jwt) {
        if (!$jwt) {
            return null;
        }

        $client = new Client();
        $data = [];

        try {
            $response = $client->post($this->params->get('FIREBASE_TOKEN_URI'), [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'verify' => $this->params->get('FIREBASE_CACERT_PATH'), 
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (!empty($data['access_token'])) {
                $this->logger->info('Firebase access token successfully received.');
            } else {
                $this->logger->error('Firebase response did not contain an access_token.', [
                    'responseBody' => $body,
                ]);
            }

        } catch (\GuzzleHttp\Exception\RequestException $e) {
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
     * Sends a Firebase Cloud Messaging (FCM) push notification to a specific device.
     * Steps:
     * 1. Builds the FCM message payload, including title, body, and custom data (qrData).
     * 2. Sends a POST request to the FCM v1 API using the provided OAuth access token.
     * 3. Handles and logs Firebase responses or errors, including HTTP status and error body.
     * 4. Returns the response body on success.
     */    
    private function sendFCM($deviceToken, $title, $body, $accessToken, $qrData) {
        $project_id = $this->params->get('FIREBASE_PROJECT_ID');

        $client = new Client();
        $url = "https://fcm.googleapis.com/v1/projects/$project_id/messages:send";

        $message = [
            "message" => [
                "token" => $deviceToken,
                "android" => [
                    "priority" => "HIGH",
                    "ttl" => "7s"
                ],     
                "apns" => [
                    "headers" => [
                        "apns-priority" => "10"
                    ],
                    "payload" => [
                        "aps" => [
                            "content-available" => 1
                        ]
                    ]
                ],
                "data"=> [
                    "title" => $title,
                    "body" => $body,
                    "action" => "show_allow_close",
                    "message"=> "Allow or Decline login to the requested domain: ",
                    "qrData" => json_encode($qrData)
                ]
            ]
        ];

        try {
            $responseFcm = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json',
                ],
                'body' => json_encode($message),
                'verify' => $this->params->get('FIREBASE_CACERT_PATH'),
            ]);

            $body = $responseFcm->getBody()->getContents();
            $this->logger->info('FCM request succeeded.', [
                'projectId' => $project_id,
                'maskedToken' => $this->maskToken($deviceToken),
                'responseBody' => $body,
            ]);
            return $body;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $context = [
                'message' => $e->getMessage(),
                'projectId' => $project_id,
                'maskedToken' => $this->maskToken($deviceToken),
                'title' => $title,
                'requestUrl' => $url,
            ];

            if ($e->hasResponse()) {
                $resp = $e->getResponse();
                $responseBody = $resp->getBody()->getContents();
                $decodedBody = json_decode($responseBody, true);

                $context['statusCode'] = $resp->getStatusCode();
                $context['responseBody'] = $responseBody;
                $context['firebaseStatus'] = $decodedBody['error']['status'] ?? null;
                $context['firebaseMessage'] = $decodedBody['error']['message'] ?? null;
                $context['firebaseCode'] = $decodedBody['error']['code'] ?? null;
                $context['firebaseErrorCode'] = $decodedBody['error']['details'][0]['errorCode'] ?? null;
            }

            $this->logger->critical('FCM request failed.', $context);
        }
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
}