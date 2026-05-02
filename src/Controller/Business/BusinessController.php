<?php

declare(strict_types=1);

/**
 * To register a new Corporate account, the user must create a Business service as prerequisite
 */
namespace App\Controller\Business;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Helper\ResponseHelper;
use App\Service\Business\BusinessCreateRequestMapper;
use App\Service\Business\BusinessCreateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/registration/corporate')]
class BusinessController extends AbstractController
{
    public function __construct(
        private readonly PayloadValidator $payloadValidator,
        private readonly BusinessCreateRequestMapper $businessCreateRequestMapper,
        private readonly BusinessCreateService $businessCreateService,
    ) {
    }

    /**
     * Create a new Business service as a prerequisite for Corporate identities
     */
    #[Route('/business/create', name: 'service_registration_business_Create', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function businessCreate(Request $request, ResponseHelper $responseHelper): Response
    {
        try {
            $validatedPayload = $this->payloadValidator->getValidatedPayload($request, 'business_create');
            $businessRequest = $this->businessCreateRequestMapper->map($validatedPayload);

            return $this->businessCreateService
                ->handle($businessRequest)
                ->toResponse();
        } catch (\Exception $exception) {
            return $responseHelper->handleException($exception);
        }
    }
}