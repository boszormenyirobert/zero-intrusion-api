<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Shared;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Shared\SharedRegistrationNewService;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityRequestMapper;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityService;
use App\Service\CredentialHub\Shared\SharedRegistrationStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/credential-hub/shared/registration')]
class SharedRegistrationController extends AbstractController
{

    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly SharedRegistrationQrIdentityRequestMapper $sharedRegistrationQrIdentityRequestMapper,
        private readonly SharedRegistrationQrIdentityService $sharedRegistrationQrIdentityService,
        private readonly SharedRegistrationNewToEncryptService $sharedRegistrationNewToEncryptService,
        private readonly SharedRegistrationNewService $sharedRegistrationNewService,
        private readonly SharedRegistrationStateService $sharedRegistrationStateService,
    ) {
    }

    #[Route('/qr-identity', name: 'shared_registration_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function sharedRegistrationQrIdentity(
        Request $request,
        ValidatorInterface $validator,
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'shared_registration_qr_identity');
            $sharedRegistrationRequest = $this->sharedRegistrationQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->sharedRegistrationQrIdentityService->handle($sharedRegistrationRequest, $validator)
            );
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/new/to-encrypt', name: 'shared_registration_new_to_encrypt', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function sharedRegistrationNewToEncrypt(
        Request $request,
    ): JsonResponse {
        try {
            return new JsonResponse($this->sharedRegistrationNewToEncryptService->handle($request)->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/new', name: 'shared_registration_new', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]    
    #[RequireJson]
    public function sharedRegistrationNew(
        Request $request,
    ): JsonResponse {
        try {
            return new JsonResponse($this->sharedRegistrationNewService->handle($request)->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/state', name: 'shared_registration_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function sharedRegistrationState(
        Request $request,
    ): JsonResponse {
        try {
            $response = $this->sharedRegistrationStateService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return $this->responseHelper->createSuccessResponse($response);
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e, [
                'registration_process_check' => 'error'
            ]);
        }
    }

    private function missingProcessResponse(): JsonResponse
    {
        return $this->responseHelper->createErrorResponse('Invalid or missing processId');
    }
}
