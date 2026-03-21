<?php

/**
 * Identity => Describe an USER, USER DEVICE
 * Device Registration Process => Start with the Mobile-Application installation finish with user-email and phone-number
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

namespace App\Controller\DeviceManagement\Identity;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\Shared\RequestService;
use App\Service\Identity\IdentityService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Helper\ResponseHelper;
use App\Exception\MissingKeyException;

#[Route('/api/secret')]
class IdentityController extends AbstractController
{

    public function __construct(
        private PayloadValidator $payloadValidator,
        private LoggerInterface $logger,
        private ResponseHelper $responseHelper,
        private RequestService $requestService,
        private IdentityService $identityService

    ) {}

    /* Called by Mobil forwarded by ProxyApi
     * 
     * First step in the device registration.
     * Generate a publicId and a privateId and (integrity)secret, credentialSecret and save after encryption in the Database
     */
    #[Route('/new', name: 'create_secret', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function createSecret(
        Request $request
    ): Response {
        try {            
            $this->payloadValidator->validatePayload($request, 'firstSecret');
            $keys = $this->identityService->getKey();
            $this->logger->critical("To the HUB Registration the registrator Public ID: " . json_encode($keys->toIdentityArray()));
            return $this->json($keys->toIdentityArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /** Called by Mobil forwarded by HUB
     * 
     * Second step in the device registration.
     * Retrive email and phone number and privacyPolicy and fcm_token from the request payload,
     * and save with the privateId and publicId and secret
     */
    #[Route('/recovery-settings', name: 'set-recovery-data', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function setRecoveryData(
        Request $request
    ): Response {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'recoverySettings');
            $this->identityService->updateIdentityRecoverySettings($validatedPayload['recoverySettings']);

            return $this->json([
                'success' => true
            ]);
        } catch (MissingKeyException $e) {
            return $this->responseHelper->handleException($e);
        }
    }
}
