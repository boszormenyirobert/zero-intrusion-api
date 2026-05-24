<?php

declare(strict_types=1);

namespace App\Controller\CredentialHub\OneTouch;

use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\CredentialHub\OneTouch\OneTouchIdentifierService;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityRequestMapper;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityService;
use App\Service\CredentialHub\OneTouch\OneTouchStateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/credential-hub/one-touch')]
class OneTouchController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly ResponseHelper $responseHelper,
        private readonly OneTouchQrIdentityRequestMapper $oneTouchQrIdentityRequestMapper,
        private readonly OneTouchQrIdentityService $oneTouchQrIdentityService,
        private readonly OneTouchIdentifierService $oneTouchIdentifierService,
        private readonly OneTouchStateService $oneTouchStateService,
    ) {
    }

    #[Route('/qr-identity', name: 'one_touch_qr_identity', methods: "POST")]
//    #[RequireHmac]
    #[RequireJson]
    public function oneTouchQrIdentity(
        Request $request,
        ValidatorInterface $validator
    ): JsonResponse {
        try {
            $validatedPayload = $this->payloadValidator->validatePayload($request, 'one_touch_qr_identity');
            $oneTouchRequest = $this->oneTouchQrIdentityRequestMapper->map($validatedPayload);

            return $this->responseHelper->createSuccessResponse(
                $this->oneTouchQrIdentityService->handle($oneTouchRequest, $validator)
            );
        } catch (\Throwable $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    #[Route('/identifier', name: 'one_touch_identifier', methods: "POST")]
//    #[RequireHmac]
//    #[MobileHmac]    
    #[RequireJson]
    public function oneTouchIdentifier(
        Request $request
    ): JsonResponse {
        try {
            return new JsonResponse($this->oneTouchIdentifierService->handle($request)->toArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }
    
    #[Route('/state', name: 'one_touch_state', methods: "POST")]
//    #[RequireHmac]
    #[RequireJson]
//    #[ExtensionHmac]
    public function oneTouchState(
        Request $request
    ): JsonResponse {
        try {
            return $this->responseHelper->createSuccessResponse(
                $this->oneTouchStateService->handle($request)
            );
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e, ['login_process_check' => false]);
        }
    }
}
