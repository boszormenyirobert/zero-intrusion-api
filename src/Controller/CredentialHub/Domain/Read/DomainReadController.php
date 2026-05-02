<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Domain\Read;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialDecryptedService;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialService;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityService;
use App\Service\CredentialHub\Domain\Read\DomainReadStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/credential-hub/domain/read')]
class DomainReadController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly DomainReadQrIdentityRequestMapper $domainReadQrIdentityRequestMapper,
        private readonly DomainReadQrIdentityService $domainReadQrIdentityService,
        private readonly DomainReadCredentialDecryptedService $domainReadCredentialDecryptedService,
        private readonly DomainReadCredentialService $domainReadCredentialService,
        private readonly DomainReadStateService $domainReadStateService,
    ) {
    }

    #[Route('/qr-identity', name: 'domain_read_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function domainReadQrIdentity(
        Request $request,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::DOMAIN_READ_QR_IDENTITY);
            $domainReadRequest = $this->domainReadQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->domainReadQrIdentityService->handle($domainReadRequest, $validator)
            );
            
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[RequireHmac]
    #[MobileHmac]
    #[RequireJson]
    #[Route('/credential/decrypted', name: 'domain_read_credential_encrypted', methods: "POST")]
    public function domainReadCredentialDecrypted(
        Request $request,
    ): JsonResponse {
        try {
            return $this->responseHelper->createSuccessResponse(
                $this->domainReadCredentialDecryptedService->handle($request)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/credential', name: 'domain_read_credential', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]
    #[RequireJson]
    public function domainReadCredential(
        Request $request,
    ): JsonResponse {
        try {
            return $this->responseHelper->createSuccessResponse(
                $this->domainReadCredentialService->handle($request)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/state', name: 'domain_read_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function domainReadState(
        Request $request,
    ): JsonResponse {
        try {
            $payload = $this->domainReadStateService->handle($request);

            if ($payload === null) {
                return $this->missingProcessResponse();
            }

            return $this->responseHelper->createSuccessResponse(
                $payload
            );

        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e, ['login_process_check' => false]);
        }
    }

    private function missingProcessResponse(): JsonResponse
    {
        return $this->responseHelper->createErrorResponse('Invalid or missing processId');
    }
}
