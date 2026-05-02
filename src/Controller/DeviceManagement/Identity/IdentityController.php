<?php

declare(strict_types=1);

/**
 * Identity => Describe an USER, USER DEVICE
 * Device Registration Process => Start with the Mobile-Application installation finish with user-email and phone-number
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

namespace App\Controller\DeviceManagement\Identity;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\Device\Identity\FirstSecretService;
use App\Service\Device\Identity\RecoverySettingsRequestMapper;
use App\Service\Device\Identity\RecoverySettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/secret')]
class IdentityController extends AbstractController
{

    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly FirstSecretService $firstSecretService,
        private readonly RecoverySettingsRequestMapper $recoverySettingsRequestMapper,
        private readonly RecoverySettingsService $recoverySettingsService,
    ) {
    }

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
            return new JsonResponse($this->firstSecretService->handle());
        } catch (\Exception $exception) {
            return $this->responseHelper->handleException($exception);
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
            $recoverySettingsRequest = $this->recoverySettingsRequestMapper->map($validatedPayload);

            return new JsonResponse($this->recoverySettingsService->handle($recoverySettingsRequest));
        } catch (\Exception $exception) {
            return $this->responseHelper->handleException($exception);
        }
    }
}
