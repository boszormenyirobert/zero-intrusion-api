<?php

declare(strict_types=1);

/**
 * To register a new Corporate account, the user must create a Business service as prerequisite
 * After that, he can create multiple Corporate identities under that Business service in the Account section
 */
namespace App\Controller\Corporate;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\Corporate\CorporateFollowUpRequestMapper;
use App\Service\Corporate\CorporateFollowUpService;
use App\Service\Corporate\CorporateIdentityInitializeRequestMapper;
use App\Service\Corporate\CorporateIdentityInitializeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/registration/corporate')]
class CorporateRegistrationController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly CorporateIdentityInitializeRequestMapper $corporateIdentityInitializeRequestMapper,
        private readonly CorporateIdentityInitializeService $corporateIdentityInitializeService,
        private readonly CorporateFollowUpRequestMapper $corporateFollowUpRequestMapper,
        private readonly CorporateFollowUpService $corporateFollowUpService,
    ) {
    }

    #[Route('/identity/create/initialize', name: 'service_registration_corporate_data', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function serviceIdentity(
        Request $request,
        ResponseHelper $responseHelper,
    ): Response
    {
        try {
            $validatedPayload = $this->payloadValidator->getValidatedPayload($request, 'getIdentity');
            $initializeRequest = $this->corporateIdentityInitializeRequestMapper->map($validatedPayload);

            return $this->corporateIdentityInitializeService
                ->handle($initializeRequest)
                ->toResponse();
        } catch (\Exception $exception) {
            return $responseHelper->handleException($exception);
        }
    }

    #[Route('/identity/create/follow-up', name: 'service_registration_corporate_data_extend', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function serviceRegistration(Request $request, ResponseHelper $responseHelper): Response
    {
        try {
            $validatedPayload = $this->payloadValidator->getValidatedPayload($request, 'updateIdentity');
            $followUpRequest = $this->corporateFollowUpRequestMapper->map($validatedPayload);

            return $this->corporateFollowUpService
                ->handle($followUpRequest)
                ->toResponse();
        } catch (\Exception $exception) {
            return $responseHelper->handleException($exception);
        }
    }
}