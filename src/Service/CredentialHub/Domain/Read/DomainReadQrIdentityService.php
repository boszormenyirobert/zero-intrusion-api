<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainReadService;
use App\DTO\CredentialHub\Domain\Read\DomainReadQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DomainReadQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly DomainReadService $domainReadService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(DomainReadQrIdentityRequestDTO $request, ValidatorInterface $validator): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('domainProcessId');
        $qrContent = $this->domainReadService->getQrContent($request->domain, $identity->getXExtensionAuthOne(), $identity);
        $errors = $validator->validate($qrContent);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->logger->critical('domainReadQrIdentity: ' . $error->getMessage());
            }
        }

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        if ($request->userPublicId !== null && $request->userPublicId !== '') {
            $this->sharedNotificationService->sendFcmNotification('domainRead', $request->userPublicId, $qrContent);
        }

        return $identity->toProcessArray('domainProcessId');
    }
}