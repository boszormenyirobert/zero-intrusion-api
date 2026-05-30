<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\Domain\Delete;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteCredentialService;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityService;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/credential-hub/domain/delete')]
class DomainDeleteController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly DomainDeleteQrIdentityRequestMapper $domainDeleteQrIdentityRequestMapper,
        private readonly DomainDeleteQrIdentityService $domainDeleteQrIdentityService,
        private readonly DomainDeleteCredentialService $domainDeleteCredentialService,
        private readonly DomainDeleteStateService $domainDeleteStateService,
    ) {
    }

    #[Route('/qr-identity', name: 'domain_delete_qr_identity', methods: "POST")]
    #[RequireJson]
    public function domainDeleteQrIdentity(
        Request $request,
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, PayloadKeys::DOMAIN_DELETE_QR_IDENTITY);
            $domainDeleteRequest = $this->domainDeleteQrIdentityRequestMapper->map(
                $validatedPayload
            );

            return $this->responseHelper->createSuccessResponse(
                $this->domainDeleteQrIdentityService->handle($domainDeleteRequest)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/credential', name: 'domain_delete_credential', methods: "POST")]
    #[RequireJson]
    public function domainDeleteCredential(
        Request $request,
    ): JsonResponse
    {
        try {
            $response = $this->domainDeleteCredentialService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return new JsonResponse($response->toArray());
            
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/state', name: 'domain_delete_state', methods: "POST")]
    #[RequireJson]
    public function domainDeleteState(
        Request $request,
    ): JsonResponse {
        try {
            $response = $this->domainDeleteStateService->handle($request);

            if ($response === null) {
                return $this->missingProcessResponse();
            }

            return $this->responseHelper->createSuccessResponse($response ?? []);
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    private function missingProcessResponse(): JsonResponse
    {
        return $this->responseHelper->createErrorResponse('Invalid or missing processId');
    }
}
