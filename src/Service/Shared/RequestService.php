<?php

namespace App\Service\Shared;

use Symfony\Component\HttpFoundation\Request;
use App\Service\Crypters\CrypterService;
use App\Helper\UtilityHelper;
use App\Repository\CorporateIdentityRepository;
use Psr\Log\LoggerInterface;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;


class RequestService
{
    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly CrypterService $crypterService,
        private readonly LoggerInterface $logger
    ) {}            

    public function requestControll(Request $request)
    {
        $this->logger->info('RequestService requestControll started.', [
            'path' => $request->getPathInfo(),
            'has_auth_header' => $request->headers->has('X-Auth'),
        ]);

        $payload = UtilityHelper::validateJsonFormat($request);
        if (array_key_exists('error', $payload)) {
            $this->logger->critical('RequestService requestControll JSON validation failed.', [
                'path' => $request->getPathInfo(),
                'error' => $payload,
            ]);

            return $payload;
        }

        $this->logger->info('RequestService requestControll JSON validation succeeded.', [
            'path' => $request->getPathInfo(),
            'payload_keys' => array_keys($payload),
        ]);

        // Validate HMAC authorization header
        $authHeader = $request->headers->get('X-Auth');

        $this->logger->info('RequestService requestControll validating auth header.', [
            'path' => $request->getPathInfo(),
            'has_iv' => array_key_exists('iv', $payload),
        ]);

        return $this->validateAuthHeader($authHeader, $payload, $payload['iv']);
    }

    /**
     * Validates the HMAC-based Authorization header
     */
    private function validateAuthHeader(string $authHeader, array $payload, string $iv): bool|array
    {
        $matches = UtilityHelper::validateAuthHeaderFormat($authHeader);
        if (array_key_exists('error', $matches)) {
            $this->logger->critical('Autheader matches: ' . json_encode((array)$matches));
            return $matches;
        }

        $this->logger->info('RequestService validateAuthHeader format validated.', [
            'payload_keys' => array_keys($payload),
        ]);
        
        $validateExpectedKey = UtilityHelper::compareExpectations(
            $matches,
            $this->params,
            $payload['zeroIntrusionProyApi'],
            $iv
        );

        if (!$validateExpectedKey) {
            $this->logger->critical('Validated expected key: ' . json_encode($validateExpectedKey));
            return $validateExpectedKey;
        }

        $this->logger->info('RequestService validateAuthHeader HMAC validation succeeded.', [
            'payload_keys' => array_keys($payload),
        ]);

        return $payload;
    }

    // Every request accepted only from the HUB application with the key: zeroIntrusionProyApi
    public function validPayload($payload)
    {
        $this->logger->info('RequestService validPayload decrypting payload.', [
            'payload_keys' => is_array($payload) ? array_keys($payload) : [],
        ]);

        $this->crypterService->setData($payload['zeroIntrusionProyApi']);
        $validatedPayload = json_decode($this->crypterService->decryptData(), true);

        $this->logger->info('RequestService validPayload decrypted payload.', [
            'validated_payload_keys' => is_array($validatedPayload) ? array_keys($validatedPayload) : [],
        ]);

        return $validatedPayload;
    }

}