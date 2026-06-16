<?php

declare(strict_types=1);

namespace App\Service\User\Qr;

use App\Service\User\UserService;
use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\DTO\User\Qr\QrIdentityResultDTO;

class QrIdentityService
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function handle(QrIdentityRequestDTO $request): QrIdentityResultDTO
    {
        return QrIdentityResultDTO::fromServiceResult(
            $this->userService->getQrDataHubUserRegistration($request->payload, $request->processKey)
        );
    }
}
