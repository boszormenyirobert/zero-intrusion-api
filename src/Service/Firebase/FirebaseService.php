<?php

namespace App\Service\Firebase;

use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use App\Repository\IdentityRepository;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;

class FirebaseService
{
        public function __construct(
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
        $client_email = "firebase-adminsdk-fbsvc@zerointrusionlock.iam.gserviceaccount.com";
        $private_key = <<<EOD
            -----BEGIN PRIVATE KEY-----
            MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQCwQFKwesPYC49u
            9t5LF8PCOhVBfB2PXg4r9rnFl4Ieo+JjY3F6SM6eD2TmHjGWXTHsQIlwGmm+j7QE
            MI0wzyPlCGssCKGFWGEvLIVHj38BtXrmlcixTE/nJ1eG5a9rTH50svqt0oqq+DkU
            UvjvC1kvuJLZZzE8HAIcx1eEezPhx4p7luyhdthwQ7UdQYuEP3k3/Weow7jbezyS
            S/crf9yMV61c1M8Qvm4PimV+NRDVLraMVVyeepdAVpMVjmm/RkKyH+TA4QGknEp6
            ezFS0SwJAiAnGfAeMpKzDzBueEmdzXxGDoMgztvTqINDAQplJmas8kcmoBpYFMrF
            sBu+jygnAgMBAAECggEALxfQwFTdFVHpbkXKGZhs9u2cFlY6c7823Cpdct1LqDIz
            4EiG3pyrkHIejJoOt9WI7E5GKszz6jXtbJ0obJ08Qwsfz7EyrzVxKjCkH/3IesVp
            5EirSixQwTuy2FlwqKPUugvEjUNPR+VxIuwUlZKbrvOLUUmQTzZQni3pRX3B3BaJ
            YjN5vxr36hYrTviHeZLhtseSlXlheB+IJVfV/e1orTsteQp2HKBlCwzFGuTlV5ZR
            yMK+aUCtm4X4IBDyu3tkruPxBz70H+NCADVasReKCzJfjRUZ5rFUm0ibBYbADYmE
            qNhzh1QRSBv0GY9MCVpD+asCh70mLPEBAv7eXAwpoQKBgQDz3K7t9/DNEawHS49Y
            K4UjS3p9aua5ew5xeGHKSJlNjWyjZ2NAtN2OWY1TpuabPQ82rKjynyR3ZsD/Cx0s
            KjD0d/QOR8tFXc/bPhstLl6WGub7Z34YYPrUcoU2qbQUYeB2+jxP6qRIUjjBui3j
            XvUt+Ie1IB2vVEWwXwk91j//EQKBgQC5BiLANt4y8YBesm135TcFKCACc17n/i7G
            JaeFSw7PwBJPlnKivK44+Gg6PUMU/zWRwIbIAAWwzFSoo5RM/+A/2ivWxvosKTfN
            BugjglC16BOlwgLsmp/5mQqmp88MDA28acoJdjv+WyjZU8tlPBqotG5eWX5mIXRY
            wK4CsOijtwKBgQCcLz1CYEgzrxvU2EoImGb/AfqDlRIMvYm0lvtayUCWcPuhdDgX
            Wz+DSku/xedwiZzS0aarLc33QzJcpsuaW7Na//CprMW9uaXEr3RMbaRa0wQZBGG4
            T3SW2HoFVo9ldoKC8SXrsUZio3aCbTGyrECvnrub/+PDRWAU4+lRV4VJYQKBgQC3
            Jjy1+lofIXHJy2OTACFjiGGPK3bxvGm+mL1ns3G48k7t22YkcxMer740kDncCfiU
            C3kfdu4rIUhYGnyNb+giLKuikhpIJpDm8gROSgvs1QrF1POiFDlxEC474/aO3Uun
            iyyECza9xKz92/WFg2Z8QwbRfFMjc9BAnpJhdY8DpwKBgBhh6wxzx3QseUhaOauj
            /1r+u7zW0r2mDpOYoCf9fZlCkZ9GZO9O0HMV7505qgdrg9+wwAnA58HpvvbxWcQ2
            mKgL/Dv6JzPZBB1EaPz9v1oCwXr8/68tLlyqU+ZVK8fM3Qh6Ev9PRr4Y7ROg0P5P
            fA+BrXiv0fEMz8H/IHl2H1bT
            -----END PRIVATE KEY-----
            EOD;

        $header = base64_encode(json_encode([
            "alg" => "RS256",
            "typ" => "JWT"
        ]));

        $now = time();
        $claim = base64_encode(json_encode([
            "iss" => $client_email,
            "scope" => "https://www.googleapis.com/auth/firebase.messaging",
            "aud" => "https://oauth2.googleapis.com/token",
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
            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'verify' => 'C:\wamp64\bin\php\php8.3.14\extras\ssl\cacert.pem', 
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
                'verify' => 'C:\wamp64\bin\php\php8.3.14\extras\ssl\cacert.pem',
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