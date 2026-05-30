<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\OneTouch;

use App\Controller\CredentialHub\Shared\SharedRegistrationService;
use App\DTO\CredentialHub\OneTouch\OneTouchQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OneTouchQrIdentityService
{
    public function __construct(
        private readonly SharedRegistrationService $sharedRegistrationService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(OneTouchQrIdentityRequestDTO $request, ValidatorInterface $validator): array
    {
        if ($request->type === null || $request->type === '') {
            throw new \InvalidArgumentException('Missing registration type');
        }

        $identity = $this->authBridgeService->generateRequestIdentity('one-touch');
        $authToken = $identity->getXExtensionAuthOne();
        $processId = $identity->getSessionId();
        $qrContent = $this->sharedRegistrationService->getOneTouchQrContent($request->toObject(), $authToken, $processId);

        $errors = $validator->validate($qrContent);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('sharedRegistrationQrIdentity: ' . $error->getMessage());
            }
        }

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        return $identity->toProcessArray('one-touch');
    }
}
