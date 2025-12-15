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
        $payload = UtilityHelper::validateJsonFormat($request);
        if (array_key_exists('error', $payload)) {
            $this->logger->critical('Identity Config 001: ' . json_encode($request));

            return $payload;
        }

        // Validate HMAC authorization header
        $authHeader = $request->headers->get('X-Auth');

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

        return $payload;
    }

    public function validPayload($payload)
    {
        //$this->logger->critical('Payload raw: ' . var_export($payload, true));        
        $this->crypterService->setData($payload['zeroIntrusionProyApi']);
        $this->logger->critical('------------------------------------------------------------zeroIntrusionProyApi success');
        return json_decode($this->crypterService->decryptData(), true);
    }

}