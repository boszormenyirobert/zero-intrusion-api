<?php

declare(strict_types=1);

namespace App\Service\User;

use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\QrInterface;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private readonly QrService $qrService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly UserQrContentFactory $userQrContentFactory,
        private readonly UserAuthorizationResponseFactory $userAuthorizationResponseFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{defaultResponse: array{headers: array<string, string>, body: string}, mobileResponse: QrInterface}
     */
    public function getQrData(array $payload, string $processKey): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('domain-read');

        $qrCodeContent = $this->userQrContentFactory->create($payload, $identity, $processKey);
        $qrCode = $this->qrService->getQrCode($qrCodeContent);
        $defaultResponse = $this->userAuthorizationResponseFactory->create(
            $this->buildExtendedIdentityPayload($identity, $qrCode)
        );

        return [
            'defaultResponse' => $defaultResponse,
            'mobileResponse' => $qrCodeContent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExtendedIdentityPayload($identity, string $qrCode): array
    {
        $payload = get_object_vars($identity);
        $payload['qrCode'] = $qrCode;

        return $payload;
    }
}