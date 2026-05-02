<?php

declare(strict_types=1);

/**
 * Restore => Device replacement and recovery process => The user have the device. E-mail or Phone-number changed
 *
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between easylogin and ProxyApi
 */

namespace App\Controller\DeviceManagement\Restore;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\Device\Restore\ReplaceDevicePinRequestMapper;
use App\Service\Device\Restore\ReplaceDevicePinService;
use App\Service\Device\Restore\ReplaceDeviceRequestMapper;
use App\Service\Device\Restore\ReplaceDeviceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/api/device')]
class RestoreController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly ReplaceDeviceRequestMapper $replaceDeviceRequestMapper,
        private readonly ReplaceDeviceService $replaceDeviceService,
        private readonly ReplaceDevicePinRequestMapper $replaceDevicePinRequestMapper,
        private readonly ReplaceDevicePinService $replaceDevicePinService,
    ) {
    }


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
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'replaceDevice');
            $replaceDeviceRequest = $this->replaceDeviceRequestMapper->map($validatedPayload);

            return new JsonResponse($this->replaceDeviceService->handle($replaceDeviceRequest));
        } catch (\Exception $exception) {
            return $this->responseHelper->handleException($exception);
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
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'restorePin');
            $replaceDevicePinRequest = $this->replaceDevicePinRequestMapper->map($validatedPayload);

            return new JsonResponse($this->replaceDevicePinService->handle($replaceDevicePinRequest));
        } catch (\Exception $exception) {
            return $this->responseHelper->handleException($exception);
        }
    }
}
