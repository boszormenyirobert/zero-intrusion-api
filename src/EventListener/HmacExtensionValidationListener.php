<?php

namespace App\EventListener;

use App\Attribute\ExtensionHmac;
use App\Repository\AuthBridgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\Crypters\CrypterService;
use Symfony\Component\HttpFoundation\JsonResponse;

class HmacExtensionValidationListener
{
    public function __construct(
        private readonly CrypterService $crypterService,
        private LoggerInterface $logger,
        private AuthBridgeRepository $authBridgeRepository,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $this->logger->critical('Start HmacExtensionValidationListener');
        
        [$controller, $method] = $event->getController();
        $reflection = new ReflectionMethod($controller, $method);
        $hasHmacCheck = !empty($reflection->getAttributes(ExtensionHmac::class));

        if (!$hasHmacCheck) {
            return;
        }

        $request = $event->getRequest();
        $authHeader = $request->headers->get('X-Extension-Auth');
        $payloadKey = $request->attributes->get('_route'); // Use route name as payload key

        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            $this->logger->critical('Invalid JSON body');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], 400));
            $event->stopPropagation();;
        }

        if (!isset($payload['iv'], $payload['zeroIntrusionProyApi'])) {
            $this->logger->critical('Missing required fields');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400));
            $event->stopPropagation();;
        }

        $this->crypterService->setData($payload['zeroIntrusionProyApi']);

        $decrypted = $this->crypterService->decryptData();
        $data = json_decode($decrypted, true);

        if (!is_array($data)) {
            $this->logger->critical('Decryption failed or returned invalid JSON.');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid decrypted payload',
            ], 400));
            $event->stopPropagation();;
        }

        $innerJson = $data[$payloadKey] ?? null;

        if ($innerJson) {
            $decoded = is_string($innerJson) ? json_decode($innerJson, true) : $innerJson;
                if (!is_array($decoded)) {
                    $this->logger->critical('Decoded innerJson is not array.');
                    $event->setController(fn() => new JsonResponse([
                        'success' => false,
                        'error' => 'Invalid inner payload',
                    ], 400));
            $event->stopPropagation();;
                }
            $processId = $decoded['processId'] ?? null;
        } else {
            $this->logger->critical('payloadKey missing or null');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'payloadKey missing or null',
            ], 400));
            $event->stopPropagation();;
        }

        if (!$authHeader || !$processId) {
            $this->logger->critical('Missing HMAC header or process ID.');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing HMAC header or process ID.',
            ], 403));
            $event->stopPropagation();;
        }

        $processKey =  $this->resolveProcessKey($payloadKey);

        if (!$processKey) {
            $this->logger->critical("Unknown payload type: $payloadKey");
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => "Unknown payload type: $payloadKey",
            ], 400));
            $event->stopPropagation();;
        }

        $process = $this->authBridgeRepository->findOneBy([
            $processKey => $processId
        ]);

        if (!$process || !$this->isHmacValid($authHeader, $process)) {
            $this->logger->critical('Invalid HMAC Extension authentication.');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid or expired HMAC from the extension',
            ], 403));
            $event->stopPropagation();
        }
    }

    private function isHmacValid(string $authHeader, $process): bool
    {
        $secret = $this->params->get('EXTENSION_REGISTRATION_POOL_SECRET');
        $message = $this->params->get('EXTENSION_REGISTRATION_POOL_MESSAGE');
        $hmacValue = $this->getHmacValue($authHeader);

            if (!is_string($hmacValue)) {
                $this->logger->error('Invalid HMAC header format.');
                return false;
            }

        $createdAt = $process->getCreatedAt()->getTimestamp();
        $now = time();
        $diff = abs($createdAt - $now);

            if ($diff > 12) {
                $this->logger->error('Time difference too large.');
                return false;
            }

        $expected = hash_hmac('sha1', $message . '|' . $createdAt, $secret);

        $isMatch = hash_equals($expected, $hmacValue);

            if (!$isMatch) {
                $this->logger->error('HMAC mismatch.');
            }

        return $isMatch;
    }

    private function getHmacValue($extensionAuthHeader): string|false
    {
        if (!$extensionAuthHeader) {
            return false;
        }

        if (str_starts_with($extensionAuthHeader, 'HMAC ')) {
            $hmac = explode(' ', $extensionAuthHeader, 2);
            return trim($hmac[1] ?? '');
        }

        return false;
    }

    private function resolveProcessKey(string $payloadKey): ?string
    {
        return match ($payloadKey) {
            'shared_registration_state' => 'registrationProcessId',
            'domain_read_state' => 'domainProcessId',
            'vault_read_state' => 'applicationProcessId',
            'domain_delete_state' => 'removeProcessId',
            'vault_delete_state' => 'removeProcessId',
            'vault_edit_state' => 'registrationProcessId',
            'domain_read_credential_encrypted' => 'domainProcessId',
            'vault_read_credential_encrypted' => 'applicationProcessId'
        };
    }
}
