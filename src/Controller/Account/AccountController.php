<?php

declare(strict_types=1);

/**
 * Handling an registrated Corporate account
 *
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between easylogin and ProxyApi
 *
 */
namespace App\Controller\Account;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Helper\ResponseHelper;
use App\Service\Account\AccountLookupService;
use App\Service\Account\AccountRequestMapper;
use App\Service\Account\AccountRequestResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/api/account')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountRequestResolver $accountRequestResolver,
        private readonly AccountRequestMapper $accountRequestMapper,
        private readonly AccountLookupService $accountLookupService,
        private readonly ResponseHelper $responseHelper,
    ) {
    }


    /*
    * Get all registrated Corporate accounts for user
    */
    #[Route('/all', name: 'account', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function account(Request $request): JsonResponse
    {
        try {
            $validatedPayload = $this->accountRequestResolver->resolve($request);
            $accountRequest = $this->accountRequestMapper->map($validatedPayload);

            return new JsonResponse(
                $this->accountLookupService
                    ->handle($accountRequest)
                    ->toArray()
            );
        } catch (\Exception $exception) {
            return $this->responseHelper->handleException($exception);
        }
    }
}