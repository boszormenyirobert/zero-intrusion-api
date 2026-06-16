<?php

namespace App\Service\Callback;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Service\Crypters\CrypterDatabaseService;

final class CallbackService
{
    public function __construct(
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient,
        private CorporateIdentityRepository $corporateIdentityRepository,
        private IdentityRepository $identityRepository,
        private CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
        private Encryptor $encryptor,
        private ContainerBagInterface $params,
        private CrypterDatabaseService $crypterDatabaseService
    ) {}

    public function callBackUserRegistration($registratedUser, $user)
    {
        $encryptedCorporate = $this->corporateIdentityRepository->findOneBy([
            'corporateId' => $user['corporateId']
        ]);

        $encryptedIdentity = $this->identityRepository->findOneBy([
            'publicId' => $registratedUser['publicId']
        ]);

        $decryptedIdentity = $this->crypterDatabaseIdentityService->decryptFromDatabase($encryptedIdentity);
        $corporateIdentity = $this->crypterDatabaseService->decryptFromDatabase($encryptedCorporate);

        $signableUserIdentity = json_encode([
            'publicId' => $user['publicId'],
            'email' => $user['email'],
        ]);

        $userIdentity = [
            'signature' => $this->signMessageWithPrivateKey($signableUserIdentity, $corporateIdentity),
            'publicId' => $user['publicId'],
            'email' => $user['email'],
            'registrationProcessId' => $user['registrationProcessId'],
            'success' => true,
        ];

        $callbackPath = $encryptedCorporate->getCallbackUserRegistration();

        try {
            $this->logger->info('Outgoing HTTP request.', [
                'channel' => 'callback',
                'operation' => 'user_registration',
                'method' => 'POST',
                'url' => $callbackPath,
                'payload' => $userIdentity,
            ]);

            // Important note:
            // verify_peer validates the TLS certificate chain and is independent from the key pair used 
            // for payload signing/encryption.            
            $response = $this->httpClient->request(
                'POST',
                $callbackPath,
                [
                    'json' => $userIdentity,
                    'verify_peer' => false,
                    'cafile' => 'C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem',                    
                ]
            );

            $this->logger->info('Outgoing HTTP response.', [
                'channel' => 'callback',
                'operation' => 'user_registration',
                'method' => 'POST',
                'url' => $callbackPath,
                'statusCode' => $response->getStatusCode(),
                'responseBody' => $response->getContent(false),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Outgoing HTTP request failed.', [
                'channel' => 'callback',
                'operation' => 'user_registration',
                'method' => 'POST',
                'url' => $callbackPath,
                'payload' => $userIdentity,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function callBackUserLogin($decryptedResponse, $user)
    {
        $encryptedCorporate = $this->corporateIdentityRepository->findOneBy([
            'corporateId' => $user['corporateId']
        ]);
        $corporateIdentity = $this->crypterDatabaseService->decryptFromDatabase($encryptedCorporate);

        $userIdentity = json_encode([
            'publicId' => $user['publicId'],
            'email' => $user['email'],
        ]);

        $userIdentitySigned =[
            'signature' => $this->signMessageWithPrivateKey($userIdentity, $corporateIdentity),
            'publicId' => $user['publicId'],
            'email' => $user['email'],
            'processId' => $user['sessionId'] ?? ($user['domainProcessId'] ?? null),
        ];

        $callbackPath = $corporateIdentity->getCallbackUserLogin();

        try{
            $this->logger->info('Outgoing HTTP request.', [
                'channel' => 'callback',
                'operation' => 'user_login',
                'method' => 'POST',
                'url' => $callbackPath,
                'payload' => $userIdentitySigned,
            ]);

            // Important note:
            // verify_peer validates the TLS certificate chain and is independent from the key pair used 
            // for payload signing/encryption.
            $response = $this->httpClient->request(
                'POST', 
                $callbackPath,
                [
                    'json' => $userIdentitySigned,
                    'verify_peer' => false,
                    'cafile' => 'C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem',
                ]
            );

            $this->logger->info('Outgoing HTTP response.', [
                'channel' => 'callback',
                'operation' => 'user_login',
                'method' => 'POST',
                'url' => $callbackPath,
                'statusCode' => $response->getStatusCode(),
                'responseBody' => $response->getContent(false),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Outgoing HTTP request failed.', [
                'channel' => 'callback',
                'operation' => 'user_login',
                'method' => 'POST',
                'url' => $callbackPath,
                'payload' => $userIdentitySigned,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function signMessageWithPrivateKey($userIdentity, $corporate)
    {
        $corporatePrivateKey = $corporate->getSslPrivateKey();
        $result = openssl_sign($userIdentity, $signature, $corporatePrivateKey, OPENSSL_ALGO_SHA256);

        if (!$result) {
            $error = openssl_error_string();
            $this->logger->error('Payload signing failed.', [
                'operation' => 'callback_signing',
                'opensslError' => $error,
            ]);
            return null; 
        }

        return base64_encode($signature);
    }
}