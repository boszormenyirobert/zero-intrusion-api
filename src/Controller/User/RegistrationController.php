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
use App\Service\User\Registration\RegistrationQrIdentityRequestMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user')]
class RegistrationController extends AbstractController
{

    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly RegistrationQrIdentityRequestMapper $registrationQrIdentityRequestMapper,
        private readonly QrIdentityService $qrIdentityService,
    ) {
    }

    /**
     * Called during the user registration process to get the QR data
     * On the HUB the QR code will be generated with the received data
     * If the userPublicId is present in the payload, an FCM notification will be sent to the user device
     * 
     * The next steps—mobile call and extension/web polling—happen in the CredentialHub.
     */
    #[Route('/registration/qr-identity', name: 'user_registration_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function registrationQrIdentity(
        Request $request,
        ResponseHelper $responseHelper
    ): Response {
        try {
            $validatedPayload = $this->payloadValidator->getValidatedPayload($request, 'user_registration');
            $qrIdentityRequest = $this->registrationQrIdentityRequestMapper->map($validatedPayload);

            return $this->qrIdentityService
                ->handle($qrIdentityRequest)
                ->toResponse();
        } catch (\Exception $exception) {
            return $responseHelper->handleException($exception);
        }
    }  
}