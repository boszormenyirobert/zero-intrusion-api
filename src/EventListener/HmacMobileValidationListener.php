<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\MobileHmac;
use App\Entity\AuthBridge;
use App\Http\ApiErrorResponseFactory;
use App\Repository\AuthBridgeRepository;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Hmac\ListenerHmacPolicy;
use App\Service\Hmac\ListenerPayloadResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use App\Service\Crypters\CrypterService;

class HmacMobileValidationListener
{
    private const INVALID_JSON_ERROR = 'Invalid JSON body';
    private const MISSING_FIELDS_ERROR = 'Missing required fields';
    private const INVALID_DECRYPTED_PAYLOAD_ERROR = 'Decryption failed or invalid structure';
    private const MISSING_PAYLOAD_KEY_ERROR = 'PayloadKey missing or null';
    private const INVALID_HMAC_ERROR = 'Invalid or expired HMAC from the extension';

    public function __construct(
        private readonly CrypterService $crypterService,
        private readonly LoggerInterface $logger,
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $params,
        private readonly ApiErrorResponseFactory $apiErrorResponseFactory,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
        private readonly ListenerHmacPolicy $listenerHmacPolicy,
        private readonly ListenerPayloadResolver $listenerPayloadResolver,
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $controllerDefinition = $event->getController();
        if (!is_array($controllerDefinition) || count($controllerDefinition) !== 2) {
            return;
        }

        [$controller, $method] = $controllerDefinition;
        $reflection = new ReflectionMethod($controller, $method);
        $hasHmacCheck = !empty($reflection->getAttributes(MobileHmac::class));

        if (!$hasHmacCheck) {
            return;
        }

        $request = $event->getRequest();
        $authHeader = $request->headers->get('X-Extension-Auth');
        $payload = $this->listenerPayloadResolver->decodeRequestPayload($request->getContent());
        $payloadKey = (string) $request->attributes->get('_route', '');

        if (!is_array($payload)) {
            $this->denyRequest($event, self::INVALID_JSON_ERROR, 400);

            return;
        }

        if (!$this->listenerPayloadResolver->hasEncryptedEnvelope($payload)) {
            $this->denyRequest($event, self::MISSING_FIELDS_ERROR, 400);

            return;
        }

        $data = $this->listenerPayloadResolver->decodeEncryptedPayload($payload);

        if (!is_array($data)) {
            $this->logger->critical('Decrypted payload is not valid JSON.');
            $this->denyRequest($event, self::INVALID_DECRYPTED_PAYLOAD_ERROR, 400);

            return;
        }

        $processKey =  $this->resolveProcessKey($payloadKey);
        $processId = $this->resolveProcessId($data, $payloadKey, $processKey);

        if ($processId === null || $processKey === null) {
            $this->denyRequest($event, self::MISSING_PAYLOAD_KEY_ERROR, 400);

            return;
        }

        $process = $this->authBridgeRepository->findOneBy([
           $processKey => $processId
        ]);

        if (!$process) {
            $this->denyRequest($event, self::INVALID_HMAC_ERROR, 403);

            return;
        }

        if (!$process || !$this->listenerHmacPolicy->validatePoolHeader($authHeader, $process, 'sha256')) {
            $this->logger->critical('Invalid HMAC Mobile authentication.');
            $this->denyRequest($event, self::INVALID_HMAC_ERROR, 403);
        }

    }

    private function resolveProcessKey(string $payloadKey): ?string
    {
        return match ($payloadKey) {
            'domain_read_credential' => 'domainProcessId',
            'vault_read_credential' => 'sessionId',
            'shared_registration_new_to_encrypt' => 'registrationProcessId',
            'shared_registration_new' => 'registrationProcessId',
            'domain_delete_credential' => 'removeProcessId',         
            'vault_delete_credential' => 'removeProcessId',       
            'vault_edit_credential' => 'registrationProcessId',
            'user_registration' => 'registrationProcessId',
            'domain_read_credential_encrypted' => 'domainProcessId',
            'vault_read_credential_encrypted' => 'sessionId',
            'one_touch_identifier' => 'sessionId',  
            default => null,
        };
    }

    private function resolveProcessId(array $data, string $payloadKey, ?string $processKey): ?string
    {
        $resolvedPayload = $this->listenerPayloadResolver->resolveDecryptedRoutePayload($data, $payloadKey);
        if ($resolvedPayload->payload === null) {
            $this->logger->critical(self::MISSING_PAYLOAD_KEY_ERROR);

            return null;
        }

        if ($processKey === null) {
            $this->logger->critical(self::MISSING_PAYLOAD_KEY_ERROR);

            return null;
        }

        $processId = $resolvedPayload->payload[$processKey] ?? null;

        return is_string($processId) && $processId !== '' ? $processId : null;
    }

    private function denyRequest(ControllerEvent $event, string $message, int $statusCode): void
    {
        $this->logger->critical($message);
        $event->setController(fn() => $this->apiErrorResponseFactory->create($message, $statusCode));
        $event->stopPropagation();
    }
}
