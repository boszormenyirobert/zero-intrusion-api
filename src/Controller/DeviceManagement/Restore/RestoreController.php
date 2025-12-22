<?php

/**
 * Restore => Device replacement and recovery process => The user have the device. E-mail or Phone-number changed
 * 
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between easylogin and ProxyApi
 */

namespace App\Controller\DeviceManagement\Restore;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Identity\IdentityService;
use App\Service\Restore\RestoreService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;


#[Route('/api/device')]
class RestoreController extends AbstractController
{
    public function __construct(
        private PayloadValidator $payloadValidator,
        private IdentityService $identityService,
        private ResponseHelper $responseHelper,
        private RestoreService $restoreService
    ) {}


    /** Called by ProxyApi
     * 
     * First step in the device replacement process.
     * Retrive email and phone number from the request payload,
     * Send email and SMS
     * Move the data to the recovery table
     */
    #[Route('/replace', name: 'replace-device', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function replaceDevice(Request $request): Response
    {
        $notifications = $this->getDefaultNotifications();

        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request);
            $secret = $this->identityService->getSecret($validatedPayload['replaceDevice']);

            if (!empty($secret)) {
                $notifications = $this->restoreService->recoveryNotification($secret);
            }

            return $this->json($notifications);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }


    /** Called by ProxyApi
     * 
     * Second step in the device replacement process.
     * Pin confirmation 
     * Return with a handy identifier // FE generates a QR code
     */
    #[Route('/replace/pin', name: 'replace-device-pin', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function replaceDeviceHash(
        Request $request
    ): Response {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request);
            $handyIdentifier = $this->restoreService->replaceValidation($validatedPayload);

            return $this->json($handyIdentifier);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    private function getDefaultNotifications()
    {
        return [
            'success' => false,
            'deviceHash' => "missing",
            "message" => "Something went wrong. Please try again later"
        ];
    }
}
