<?php

declare(strict_types=1);

namespace App\Service\User;

use App\DTO\QR\CorporateRegistrationDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\QrInterface;
use App\DTO\QR\UserLoginDTO;
use App\Exception\CorporateRegistrationException;
use App\Service\Shared\ProcessTypeNormalizer;
use Psr\Log\LoggerInterface;

class UserQrContentFactory
{
    private const REGISTRATION_PROCESS_ID = 'registrationProcessId';
    private const DOMAIN_PROCESS_ID = 'domainProcessId';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ProcessTypeNormalizer $processTypeNormalizer,
    ) {
    }

    public function create(array $payload,  $identity, string $processKey): QrInterface
    {
        $this->logger->critical('Creating QR content for user with process key: ' . $processKey, [
            'payload' => json_encode($payload),
            'identity' => json_encode($identity),
            'processKey' => $processKey,
        ]);

        return match ($processKey) {
            self::REGISTRATION_PROCESS_ID => $this->createRegistrationContent($payload, $identity),
            self::DOMAIN_PROCESS_ID => $this->createLoginContent($payload, $identity),
            default => throw new CorporateRegistrationException(sprintf('Unsupported process key: %s', $processKey)),
        };
    }

    private function createRegistrationContent(array $payload, CredentialHubIdentityDTO $identity): CorporateRegistrationDTO
    {
        $corporateAuthentication = $payload['corporateAuthentication'] ?? null;

        if (is_array($corporateAuthentication) && count($corporateAuthentication) > 1) {
            $this->logger->warning('Registration payload received multiple corporate authentication values; using the first entry.');
        }

        $newCorporateRegistration = new CorporateRegistrationDTO();
        $newCorporateRegistration->setCorporateId($payload['corporatePublicId'] ?? null);
        $newCorporateRegistration->setCorporateAuthentication($this->resolveFirstCorporateAuthentication($payload));
        $newCorporateRegistration->setDomain($payload['domain'] ?? null);
        $newCorporateRegistration->setXExtensionAuthOne($identity->getXExtensionAuthOne());
        $newCorporateRegistration->setRegistrationProcessId($identity->getRegistrationProcessId());
        $newCorporateRegistration->setType('system_hub_registration');
        $newCorporateRegistration->setIsNew('new');

        return $newCorporateRegistration;
    }

    private function createLoginContent(array $payload,  $identity): UserLoginDTO
    {
        $this->logger->critical(' DTO start');

        $processId = $this->processTypeNormalizer->resolveProcessId(
            $identity->getSessionId(),
            $identity->getDomainProcessId(),
        );

        $dto = new UserLoginDTO(
            $payload['domain'] ?? null,
            $processId,
            $identity->getXExtensionAuthOne(),
            'system_hub_login',
            $payload['corporatePublicId'] ?? null,
            $payload['corporateAuthentication'] ?? null,
            'corporate',
        );
        $this->logger->debug(' DTO created for user login QR content', ['dto' => $dto]);
        return $dto;
    }

    private function resolveFirstCorporateAuthentication(array $payload): ?string
    {
        $corporateAuthentication = $payload['corporateAuthentication'] ?? null;

        if (is_array($corporateAuthentication)) {
            $firstAuthentication = reset($corporateAuthentication);

            return is_string($firstAuthentication) ? $firstAuthentication : null;
        }

        return is_string($corporateAuthentication) ? $corporateAuthentication : null;
    }
}