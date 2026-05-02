<?php

declare(strict_types=1);

/**
 * User registration/login on the HUB or on any registrated WEB site
 *
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

namespace App\Controller\User;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\User\Qr\QrIdentityService;
use App\Service\User\SecureDevice\SecureDeviceQrIdentityRequestMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user')]
class SecureDeviceController extends AbstractController
{

    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly SecureDeviceQrIdentityRequestMapper $secureDeviceQrIdentityRequestMapper,
        private readonly QrIdentityService $qrIdentityService,
    ) {
    }

     /** 
     * Generate QR for "One Touch Activation" link
     */
    #[Route('/secure-device/qr-identity', name: 'secure_device_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function secureDeviceQrIdentity(
        Request $request,
        ResponseHelper $responseHelper
    ): Response {
        try {
            $validatedPayload = $this->payloadValidator->getValidatedPayload($request, 'secure_device_registration');
            $qrIdentityRequest = $this->secureDeviceQrIdentityRequestMapper->map($validatedPayload);

            return $this->qrIdentityService
                ->handle($qrIdentityRequest)
                ->toResponse();
        } catch (\Exception $exception) {
            return $responseHelper->handleException($exception);
        }
    }        
}