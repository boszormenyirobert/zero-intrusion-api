<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Domain\Read;

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
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Service\CredentialHub\SharedSSE;

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
        private readonly SharedSSE $sharedSSE
    ) {
    }

    #[Route('/qr-identity', name: 'domain_read_qr_identity', methods: "POST")]
    #[RequireJson]
    public function domainReadQrIdentity(
        Request $request,
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::DOMAIN_READ_QR_IDENTITY);
            $domainReadRequest = $this->domainReadQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->domainReadQrIdentityService->handle($domainReadRequest)
            );
            
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

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

    #[Route('/approval-challange/{key}', name: 'api_sse_domain', methods: ['GET'])]
    public function sse(string $key): StreamedResponse
    {
        return $this->sharedSSE->handle($key);
    }
}
