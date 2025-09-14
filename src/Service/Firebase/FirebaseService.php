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

    public function manageFcm($deviceToken, $title, $body, $qrData)
    {
        $identityEncrypted = $this->identityRepository->findOneBy(['publicId' => $deviceToken]);
        if($identityEncrypted){
            $identity = $this->crypterDatabaseIdentityService->decryptFromDatabase($identityEncrypted);
            $this->sendFcmMessage($identity->getFcmToken(), $title, $body, $qrData);
        }
    }

    public function sendFcmMessage($deviceToken, $title, $body, $qrData) {        
        $jwt = $this->getJwt();
        $accessToken = $this->getAccessToken($jwt);
        $this->sendFCM($deviceToken, $title, $body, $accessToken, $qrData);
    }

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

       // 2. JWT aláírása
        openssl_sign("$header.$claim", $signature, $private_key, "SHA256");
        $jwt = "$header.$claim." . base64_encode($signature);

        return $jwt;
    }

    private function getAccessToken($jwt) {
        $client = new Client();
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
       //        $this->logger->critical('access_token response: ' . $data['access_token']);

            } else {
               $this->logger->critical('hiba: ');
            }

        } catch (\GuzzleHttp\Exception\RequestException $e) {
             $this->logger->critical('hiba: '.$e->getMessage());
            echo "Hiba: " . $e->getMessage();
        }

        return $data["access_token"];       
    }

    private function sendFCM($deviceToken, $title, $body, $accessToken, $qrData) {
        $project_id = "zerointrusionlock";

        $client = new Client();
        $url = "https://fcm.googleapis.com/v1/projects/$project_id/messages:send";

        $message = [
            "message" => [
                "token" => $deviceToken,
                "notification" => [
                    "title" => $title,
                    "body" => $body
                ],                
                "data"=> [
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
    //        $this->logger->critical('FCM Response: ' . $body);
            return $body;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->logger->critical('FCM Hiba: ' . $e->getMessage());

            if ($e->hasResponse()) {
                $resp = $e->getResponse();
                $this->logger->critical('FCM Error Status: ' . $resp->getStatusCode());
                $this->logger->critical('FCM Error Body: ' . $resp->getBody()->getContents());
            }
        }
    }
}