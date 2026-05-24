<?php

declare(strict_types=1);

namespace App\Service\User\Login;

use App\Service\User\UserService;
use App\DTO\User\Login\LoginQrIdentityRequestDTO;
use App\DTO\User\Login\LoginQrIdentityResultDTO;
use App\Service\Firebase\FirebaseService;
use Psr\Log\LoggerInterface;

class LoginQrIdentityService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly FirebaseService $firebaseService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(LoginQrIdentityRequestDTO $request): LoginQrIdentityResultDTO
    {
        $data = $this->userService->getQrData($request->toPayload(), 'domainProcessId');
        $result = LoginQrIdentityResultDTO::fromServiceResult($data);

        if (!$request->hasUserPublicId()) {
            return $result;
        }

        $this->firebaseService->manageFcm(
            $request->userPublicId,
            'Test Title',
            'Test Body',
            $result->mobileResponse,
        );

        return $result;
    }

    private function extractProcessId(mixed $mobileResponse): ?string
    {
        if (is_object($mobileResponse) && property_exists($mobileResponse, 'domainProcessId')) {
            $processId = $mobileResponse->domainProcessId;

            return is_string($processId) ? $processId : null;
        }

        if (is_array($mobileResponse) && isset($mobileResponse['domainProcessId']) && is_string($mobileResponse['domainProcessId'])) {
            return $mobileResponse['domainProcessId'];
        }

        return null;
    }
}
