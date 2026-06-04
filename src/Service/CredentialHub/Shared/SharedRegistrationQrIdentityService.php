<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Service\CredentialHub\Shared\SharedRegistrationService;
use App\DTO\CredentialHub\Shared\SharedRegistrationQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use App\DTO\CredentialHub\ExtensionCredentialRequestDTO;
use App\DTO\CredentialHub\QrContentDTO;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\CredentialReadService;
use App\Service\CredentialHub\IdentityType;

class SharedRegistrationQrIdentityService
{
    public function __construct(
        private readonly SharedRegistrationService $sharedRegistrationService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly LoggerInterface $logger,

        private readonly ProcessStateCacheService $processStateCacheService,
        private readonly CredentialReadService $credentialReadService,
    ) {
    }
    
    public function handle(ExtensionCredentialRequestDTO $request, IdentityType $type): array
    {
        $identity = $this->credentialReadService->getIdentity($request, $type);       
        $qrCacheKey = $identity->getQrCacheKey();
        $qrContent = $this->processStateCacheService->get($qrCacheKey);

        if (!$qrContent instanceof QrContentDTO) {
            throw new \RuntimeException(sprintf('Missing or invalid QR content in cache for key: %s', (string) $qrCacheKey));
        }

        $this->credentialReadService->handleNotification($request, $identity, $type, $qrContent);
        $qrCode = $identity->toProcessArray($type->value);

        return $qrCode;
    }

    public function handleOriginal(SharedRegistrationQrIdentityRequestDTO $request, ValidatorInterface $validator): array
    {
        if ($request->type === null || $request->type === '') {
        //    throw new \InvalidArgumentException('Missing registration type');
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
        $identity->setType('registrationProcessId');
        return $identity->toRegistrationProcessArray();
    }
}