<?php

namespace App\Controller\CredentialHub\Domain\Delete;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\AuthBridge\AuthBridgeService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Helper\ResponseHelper;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Attribute\RequireHmac;
use App\Attribute\ExtensionHmac;
use App\Attribute\MobileHmac;
use App\Attribute\RequireJson;
use App\Service\QrService\QrService;
use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\Domain\Delete\DomainDeleteService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\DTO\QR\DomainDeleteQrContentDTO;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Controller\CredentialHub\SharedService;

#[Route('/api/credential-hub/domain/delete')]
class DomainDeleteController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private PayloadValidator $payloadValidator,
        private ResponseHelper $responseHelper,
        private ValidatorInterface $validator,
        private SharedService $sharedService
    ) {}

    /**
     * Generates a QR code for domain deletion.
     * Called by the Browser Extension.
     */
    #[Route('/qr-identity', name: 'domain_delete_qr_identity', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    public function domainDeleteQrIdentity(
        Request $request,
        AuthBridgeService $authBridgeService,
        QrService $qrService,
        DomainDeleteService $domainDeleteService
    ): JsonResponse {
        $processKey = PayloadKeys::REMOVE_PROCESS_ID;
        $payloadKey = PayloadKeys::DOMAIN_DELETE_QR_IDENTITY;

        try {
            $validatedPayloadJson = $this->payloadValidator->validatePayload($request, $payloadKey);
            $validatedPayload = json_decode($validatedPayloadJson[$payloadKey],true);
            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */
            $identity = $authBridgeService->generateRequestIdentity($processKey);

            /** @var DomainDeleteQrContentDTO */
            $qrContent = $domainDeleteService->getQrContent($validatedPayload, $identity->getXExtensionAuthOne(), $identity->getRemoveProcessId());

            $errors = $this->validator->validate($qrContent);

            if (count($errors) > 0) {                
                foreach ($errors as $error) {
                    echo $error->getPropertyPath() . ': ' . $error->getMessage();
                }
            }
            $qrCode = $qrService->getQrCode($qrContent);
            $identity->setQrCode($qrCode);
            if(isset($validatedPayload['userPublicId']) && $validatedPayload['userPublicId'])
            { 
                $this->sharedService->sendFcmNotification(
                    'domainDelete',
                    $validatedPayload['userPublicId'],
                    $qrContent
                );
            }
            return $this->responseHelper->createSuccessResponse($identity->toRemoveProcessArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Handles credential-based domain deletion.
     * Called by the Mobile App.
     */
    #[Route('/credential', name: 'domain_delete_credential', methods: "POST")]
    #[RequireHmac]
    #[MobileHmac]
    #[RequireJson]
    public function domainDeleteCredential(
        Request $request,
        DomainDeleteService $domainDeleteService,
        ): JsonResponse
    {
         $payloadKey = PayloadKeys::DOMAIN_DELETE_CREDENTIAL;

        try {
            $process = $this->sharedService->getProcessId($request, $payloadKey, true);

            if(!$process) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }
            $this->logger->critical('DomainDeleteController: domainDeleteCredential processId '.$process );
            $response = $domainDeleteService->deleteDomain($process);
            
            return $this->json([
                'delete_process' => $response,
                'error' => ''
            ]);
            // return $this->responseHelper->createSuccessResponse($response);
            
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }

    /**
     * Checks whether the domain deletion process has completed.
     * Called by the Browser Extension.
     */
    #[Route('/state', name: 'domain_delete_state', methods: "POST")]
    #[RequireHmac]
    #[RequireJson]
    #[ExtensionHmac]
    public function domainDeleteState(
        Request $request,
        AccessRegistryRegistrationService $stateService,
    ): JsonResponse {
        $payloadKey = PayloadKeys::DOMAIN_DELETE_STATE;
        $processKey = PayloadKeys::REMOVE_PROCESS_ID;

        try {
            $processId = $this->sharedService->getProcessId($request, $payloadKey);

            if(!$processId) {
                return $this->responseHelper->createErrorResponse('Invalid or missing processId');
            }

            $response = $stateService->getState($processId, $processKey);

            return $this->responseHelper->createSuccessResponse($response->toStateArray());
        } catch (\Exception $e) {
            return $this->responseHelper->handleException($e);
        }
    }
}
