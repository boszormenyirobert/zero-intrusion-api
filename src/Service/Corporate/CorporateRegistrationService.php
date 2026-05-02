<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\Entity\CorporateIdentity;
use App\Exception\CorporateRegistrationException;
use App\Repository\BusinessServicesRepository;
use App\Repository\CorporateIdentityRepository;
use App\Service\Shared\RequestService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class CorporateRegistrationService
{
    /** @var array<string, string> */
    private const BUSINESS_SUBSCRIPTION_MAP = [
        'pro' => 'businessPro',
        'plus' => 'businessPlus',
        'basic' => 'businessBasic',
        'biometric' => 'biometric',
        'passwordManager' => 'passwordManager',
    ];

    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private readonly IdentityService $identityService,
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly \App\Service\Crypters\CrypterService $crypterService,
        private readonly LoggerInterface $logger,
        private readonly RequestService $requestService,
        private readonly BusinessServicesRepository $businessServicesRepository,
        private readonly CorporateAuthorizedResponseFactory $corporateAuthorizedResponseFactory,
    ) {
    }


    public function getBusinessRegistration(array $data): array
    {
        return $this->businessRegistrationHandler()->handle($data);
    }

    /**
     * Send identifier data-set to ProxyApi
     *
     * - Authorization: SERVICE_API_KEY + SERVICE_API_SECRET
     * - Encryption:    DATA_HASH_SECRET
     */
    public function getSubscriptionData(array $data): array
    {
        return $this->subscriptionInitializationHandler()->handle($data);
    }

    public function updateSubscriptionData(array $corporateFollowUpData): CorporateIdentity|array
    {
        try {
            return $this->updateSubscriptionDataOrFail($corporateFollowUpData);
        } catch (CorporateRegistrationException $exception) {
            $errorPayload = [
                'error' => true,
                'message' => $exception->getMessage(),
            ];

            if ($exception->getErrorData() !== []) {
                $errorPayload['data'] = $exception->getErrorData();
            }

            return $errorPayload;
        }
    }

    public function updateSubscriptionDataOrFail(array $corporateFollowUpData): CorporateIdentity
    {
        return $this->followUpUpdater()->handle($corporateFollowUpData);
    }

    public function getSelectedSubscription(mixed $businessSubscription): ?string
    {
        $this->logger->info('CorporateRegistrationService getSelectedSubscription started.', [
            'business_subscription_id' => is_object($businessSubscription) && method_exists($businessSubscription, 'getId')
                ? $businessSubscription->getId()
                : $businessSubscription,
        ]);

        $business = $this->resolveBusinessSubscription($businessSubscription);

        if ($business === null) {
            $this->logger->warning('CorporateRegistrationService getSelectedSubscription could not resolve business model.', [
                'business_subscription_id' => is_scalar($businessSubscription) ? $businessSubscription : null,
            ]);

            return null;
        }

        foreach (self::BUSINESS_SUBSCRIPTION_MAP as $property => $businessModel) {
            $getter = 'is' . ucfirst($property);

            if ($property === 'passwordManager') {
                $getter = 'isPasswordManager';
            }

            if ($business->$getter() === true) {
                $this->logger->info('CorporateRegistrationService getSelectedSubscription resolved business model.', [
                    'business_subscription_id' => $business->getId(),
                    'business_model' => $businessModel,
                ]);

                return $businessModel;
            }
        }

        $this->logger->warning('CorporateRegistrationService getSelectedSubscription could not resolve business model.', [
            'business_subscription_id' => $business->getId(),
        ]);

        return null;
    }

    public function accessDataByKey(array $payload, string $key): array
    {
        $this->logger->info('CorporateRegistrationService accessDataByKey started.', [
            'key' => $key,
            'payload_keys' => array_keys($payload),
        ]);

        $validatedPayload = $this->requestService->validPayload($payload);
        $dataJson = $validatedPayload[$key];

        if (!is_string($dataJson) || $dataJson === '') {
            throw new \InvalidArgumentException(sprintf('Payload segment "%s" must be a non-empty JSON string.', $key));
        }

        try {
            $resolvedData = json_decode($dataJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(sprintf('Payload segment "%s" must contain valid JSON.', $key), 0, $exception);
        }

        if (!is_array($resolvedData)) {
            throw new \InvalidArgumentException(sprintf('Payload segment "%s" must decode to an array.', $key));
        }

        $this->logger->info('CorporateRegistrationService accessDataByKey resolved payload segment.', [
            'key' => $key,
            'resolved_keys' => is_array($resolvedData) ? array_keys($resolvedData) : [],
            'public_id' => $resolvedData['publicId'] ?? null,
            'scope' => $resolvedData['scope'] ?? null,
        ]);

        return $resolvedData;
    }

    private function encryptAndBuildResponse(array $payload): array
    {
        return $this->corporateAuthorizedResponseFactory->create($payload);
    }

    private function businessRegistrationHandler(): CorporateBusinessRegistrationHandler
    {
        return new CorporateBusinessRegistrationHandler(
            $this->corporateRegistrationDatabaseService,
            $this->corporateAuthorizedResponseFactory,
        );
    }

    private function subscriptionInitializationHandler(): CorporateSubscriptionInitializationHandler
    {
        return new CorporateSubscriptionInitializationHandler(
            $this->identityService,
            $this->corporateRegistrationDatabaseService,
            $this->corporateAuthorizedResponseFactory,
        );
    }

    private function followUpUpdater(): CorporateFollowUpUpdater
    {
        return new CorporateFollowUpUpdater(
            $this->corporateIdentityRepository,
            $this->corporateRegistrationDatabaseService,
        );
    }

    private function resolveBusinessSubscription(mixed $businessSubscription): ?object
    {
        if ($businessSubscription instanceof \App\Entity\BusinessServices) {
            return $businessSubscription;
        }

        if (is_int($businessSubscription)) {
            $resolved = $this->businessServicesRepository->findOneBy(['id' => $businessSubscription]);

            return $resolved instanceof \App\Entity\BusinessServices ? $resolved : null;
        }

        return null;
    }
}
