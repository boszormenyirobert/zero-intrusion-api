<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Controller\CredentialHub\Shared\SharedRegistrationService;
use App\DTO\CredentialHub\Shared\SharedRegistrationQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SharedRegistrationQrIdentityService
{
    public function __construct(
        private readonly SharedRegistrationService $sharedRegistrationService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(SharedRegistrationQrIdentityRequestDTO $request, ValidatorInterface $validator): array
    {
        if ($request->type === null || $request->type === '') {
            throw new \InvalidArgumentException('Missing registration type');
        }

        $identity = $this->authBridgeService->generateRequestIdentity('registrationProcessId');
        $payload = $request->toObject();
        $authToken = $identity->getXExtensionAuthOne();
        $processId = $identity->getRegistrationProcessId();

        $this->sharedRegistrationService->saveUserCredentialInAuthBridge($payload, $processId);

        $qrContent = $this->sharedRegistrationService->getQrContent($payload, $authToken, $processId);
        $errors = $validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('sharedRegistrationQrIdentity: ' . $error->getMessage());
            }
        }

        $extendedQrContent = $this->sharedRegistrationService->getExtendedQrContent($request->type, $qrContent, $payload);
        $identity->setQrCode($this->qrService->getQrCode($extendedQrContent));

        if ($request->userPublicId !== null && $request->userPublicId !== '') {
            $this->sharedNotificationService->sendFcmNotification('sharedRegistration', $request->userPublicId, $qrContent);
        }

        return $identity->toRegistrationProcessArray();
    }
}