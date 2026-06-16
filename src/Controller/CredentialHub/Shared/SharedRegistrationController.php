<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Shared;

use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Shared\SharedRegistrationNewService;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityRequestMapper;
use App\Service\CredentialHub\ReadQrIdentityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Service\CredentialHub\SharedSSE;
use App\Service\CredentialHub\IdentityType;

#[Route('/api/credential-hub/shared/registration')]
class SharedRegistrationController extends AbstractController
{

    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly SharedRegistrationQrIdentityRequestMapper $sharedRegistrationQrIdentityRequestMapper,
        private readonly ReadQrIdentityService $readQrIdentityService,
        private readonly SharedRegistrationNewToEncryptService $sharedRegistrationNewToEncryptService,
        private readonly SharedRegistrationNewService $sharedRegistrationNewService,
        private readonly SharedSSE $sharedSSE,
    ) {
    }

    // Generate the sessionId, send challenge, and show QR-code to retrieve a publicKey from the mobile app
    #[Route('/qr-identity', name: 'shared_registration_qr_identity', methods: "POST")]
    #[RequireJson]
    public function sharedRegistrationQrIdentity(
        Request $request,
        ValidatorInterface $validator,
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_qr_identity');
            $sharedRegistrationRequest = $this->sharedRegistrationQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(                 
                $this->readQrIdentityService->handle($sharedRegistrationRequest, IdentityType::NEW_USER_CREDENTIAL)
            );
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    // Mobile calls back with the publicKey, we save in Redis the sessionId and publicKey, and send an event to the SSE connection to notify the web client
    #[Route('/new/to-encrypt', name: 'shared_registration_new_to_encrypt', methods: "POST")]
     #[RequireJson]
    public function sharedRegistrationNewToEncrypt(
        Request $request,
    ): JsonResponse {
        try {
            return $this->responseHelper->createSuccessResponse([
                'credentials' => $this->sharedRegistrationNewToEncryptService->handle($request),
            ]);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    // Extension call. Get the credential encrypted with the publicKey
    // Send an notification to the mobile app
    // Payload contains the sessionId, encryptedAesKey, encryptedData, and iv
    // The notification is "silent" on the mobile app
    #[Route('/new', name: 'shared_registration_new', methods: "POST")]
    #[RequireJson]
    public function sharedRegistrationNew(
        Request $request,
    ): JsonResponse {
        try {
            return new JsonResponse($this->sharedRegistrationNewService->handleCredentialRegistration($request)->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    // Mobil call to save the credential/ Add new HUB-User
    #[Route('/new/save', name: 'shared_registration_new_save', methods: "POST")]
    #[RequireJson]
    public function sharedRegistrationNewSave(
        Request $request,
    ): JsonResponse {
        try {
            return new JsonResponse($this->sharedRegistrationNewService->handleSave($request)->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }    

    //Extension connection by clicking to get user credential. Return by the sessionId
    #[Route('/approval-challange/{key}', name: 'api_sse_create', methods: ['GET'])]
    public function sse(string $key): StreamedResponse
    {
        return $this->sharedSSE->handle($key);
    }
}
