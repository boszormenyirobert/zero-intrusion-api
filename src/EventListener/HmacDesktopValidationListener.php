<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\DesktopHmac;
use App\Http\ApiErrorResponseFactory;
use App\Repository\AuthBridgeRepository;
use App\Repository\CorporateIdentityRepository;
use App\Service\Crypters\CrypterDatabaseService;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\DesktopHmacPolicy;
use App\Service\Hmac\ListenerPayloadResolver;
use App\Service\Payload\JsonPayloadDecoder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;

class HmacDesktopValidationListener
{
    private const INVALID_JSON_ERROR = 'Invalid JSON body';
    private const MISSING_FIELDS_ERROR = 'Missing required fields';
    private const INVALID_DECRYPTED_PAYLOAD_ERROR = 'Invalid decrypted payload';
    private const INVALID_INNER_PAYLOAD_ERROR = 'Invalid inner payload';
    private const MISSING_PAYLOAD_KEY_ERROR = 'payloadKey missing or null';
    private const MISSING_HMAC_FIELDS_ERROR = 'Missing required HMAC fields';
    private const INVALID_SIGNATURE_ERROR = 'Invalid HMAC signature';
    private const INVALID_TIMESTAMP_ERROR = 'Timestamp is outside the allowed window';

    public function __construct(
        private readonly CrypterService $crypterService,
        private readonly LoggerInterface $logger,
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $params,
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly CrypterDatabaseService $crypterDatabaseService,
        private readonly ApiErrorResponseFactory $apiErrorResponseFactory,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
        private readonly DesktopHmacPolicy $desktopHmacPolicy,
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
        $hasHmacCheck = !empty($reflection->getAttributes(DesktopHmac::class));

        if (!$hasHmacCheck) {
            return;
        }

        $request = $event->getRequest();
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
            $this->logger->critical('Decryption failed or returned invalid JSON.');
            $this->denyRequest($event, self::INVALID_DECRYPTED_PAYLOAD_ERROR, 400);

            return;
        }

        $payloadDecoded = $this->listenerPayloadResolver->resolveDecryptedRoutePayload($data, $payloadKey);
        if ($payloadDecoded->invalidInnerPayload) {
            $this->denyRequest($event, self::INVALID_INNER_PAYLOAD_ERROR, 400);

            return;
        }

        if ($payloadDecoded->missingPayloadKey || $payloadDecoded->payload === null) {
            $this->denyRequest($event, self::MISSING_PAYLOAD_KEY_ERROR, 400);

            return;
        }

        $recvSignature = strtolower((string) $request->headers->get('x-extension-auth', ''));

        $corporateId = $payloadDecoded->payload['publicId'] ?? null;
        $timestamp = $payloadDecoded->payload['timestamp'] ?? null;

        if (!is_string($corporateId) || $corporateId === '' || !is_numeric((string) $timestamp) || $recvSignature === '') {
            $this->denyRequest($event, self::MISSING_HMAC_FIELDS_ERROR, 400);

            return;
        }

        $corporateDbEncrypted = $this->corporateIdentityRepository->findOneBy(['corporateId' => $corporateId]);
        if ($corporateDbEncrypted === null) {
            $this->denyRequest($event, self::INVALID_SIGNATURE_ERROR, 400);

            return;
        }

        $corporate = $this->crypterDatabaseService->decryptFromDatabase($corporateDbEncrypted);

        if (!$this->desktopHmacPolicy->validateSignature($recvSignature, $corporateId, (int) $timestamp, $corporate)) {
            $this->denyRequest($event, self::INVALID_SIGNATURE_ERROR, 400);
            return;
        }

        if (!$this->desktopHmacPolicy->isTimestampWithinWindow((int) $timestamp)) {
            $this->denyRequest($event, self::INVALID_TIMESTAMP_ERROR, 400);
            return;
        }
    }
    private function denyRequest(ControllerEvent $event, string $message, int $statusCode): void
    {
        $this->logger->critical($message);
        $event->setController(fn() => $this->apiErrorResponseFactory->create($message, $statusCode));
        $event->stopPropagation();
    }
}
