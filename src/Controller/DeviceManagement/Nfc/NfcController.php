<?php

declare(strict_types=1);

/**
 * NFC => Communication with the Desktop Applicaiton
 *
 * All communication through the HUB
 *
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between HUB and API
 */

namespace App\Controller\DeviceManagement\Nfc;

use App\Attribute\DesktopHmac;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Helper\ResponseHelper;
use App\Service\Device\Nfc\NfcDecryptRequestMapper;
use App\Service\Device\Nfc\NfcDecryptService;
use App\Service\Device\Nfc\NfcRequestResolver;
use App\Service\Device\Nfc\NfcUsersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;



class NfcController extends AbstractController
{
    public function __construct(
        private readonly NfcUsersService $nfcUsersService,
        private readonly NfcRequestResolver $nfcRequestResolver,
        private readonly NfcDecryptRequestMapper $nfcDecryptRequestMapper,
        private readonly NfcDecryptService $nfcDecryptService,
        private readonly ResponseHelper $responseHelper,
    ) {
    }

        /**
         * Used by the Desktop Application to fetch all NFC users
         * On the Desktop Application the encrypted NFC data will be written on the NFC card by the selected user
         */
        #[Route('/api/nfc/users', name: 'api_nfc_users', methods: "POST")]
        #[RequireHmac]
        #[DesktopHmac]
        #[RequireJson]
        public function getNfcUsers(Request $request): JsonResponse
        {
            try {
                return new JsonResponse($this->nfcUsersService->handle());
            } catch (\Exception $exception) {
                return $this->responseHelper->handleException($exception);
            }
        }

        /**
         * Used by the Desktop Application to decrypt the NFC card data read from the NFC card
         * On the Desktop Application the encrypted NFC data will be generated as a QR code
         */
        #[Route('/api/nfc/decrypt', name: 'api_nfc_decrypt', methods: "POST")]
        #[RequireHmac]
        #[DesktopHmac]
        #[RequireJson]
        public function decryptNfcCardData(Request $request): JsonResponse
        {
            try {
                $validatedPayload = $this->nfcRequestResolver->resolve($request);
                $decryptRequest = $this->nfcDecryptRequestMapper->map($validatedPayload);

                return new JsonResponse($this->nfcDecryptService->handle($decryptRequest));
            } catch (\Exception $exception) {
                return $this->responseHelper->handleException($exception);
            }
        }
}