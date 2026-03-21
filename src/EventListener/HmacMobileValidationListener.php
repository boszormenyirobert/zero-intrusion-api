<?php

namespace App\EventListener;

use App\Attribute\MobileHmac;
use App\Repository\AuthBridgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\Crypters\CrypterService;
use Symfony\Component\HttpFoundation\JsonResponse;

class HmacMobileValidationListener
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
        [$controller, $method] = $event->getController();
        $reflection = new ReflectionMethod($controller, $method);
        $hasHmacCheck = !empty($reflection->getAttributes(MobileHmac::class));

        if (!$hasHmacCheck) {
            return;
        }

        $request = $event->getRequest();
        $authHeader = $request->headers->get('X-Extension-Auth');
        $payload = json_decode($request->getContent(), true);
        $payloadKey = $request->attributes->get('_route');

        if (!is_array($payload)) {
            $this->logger->critical('Invalid JSON body');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], 400));
            $event->stopPropagation();
        }

        if (!isset($payload['iv'], $payload['zeroIntrusionProyApi'])) {
            $this->logger->critical('Missing required fields');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400));
            $event->stopPropagation();
        }

        $this->crypterService->setData($payload['zeroIntrusionProyApi']);
        $data = json_decode($this->crypterService->decryptData(), true);

        if (!is_array($data)) {
            $this->logger->critical('Decrypted payload is not valid JSON.');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Decryption failed or invalid structure',
            ], 400));
            $event->stopPropagation();
        }

        $processKey =  $this->resolveProcessKey($payloadKey);
        $innerJson = $data[$payloadKey] ?? null;
        if ($innerJson) {
                if (is_string($innerJson)) {
                    $innerJson = json_decode($innerJson, true);
                }
            $processId = $innerJson[$processKey] ?? null;
        } else {
            $this->logger->critical('payloadKey missing or null');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'payloadKey missing or null',
            ], 400));
            return;
        }

        $process = $this->authBridgeRepository->findOneBy([
           $processKey => $processId
        ]);

        if (!$process) {
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid or expired HMAC from the extension',
            ], 403));
            $event->stopPropagation();
        }

        if (!$process || !$this->isHmacValid($authHeader, $process)) {
            $this->logger->critical('Invalid HMAC Mobile authentication.');
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
        $expected = hash_hmac('sha256', $message . '|' . $createdAt, $secret);
        $isMatch = hash_equals($expected, $hmacValue);

            if (!$isMatch) {
                $this->logger->critical('HMAC mismatch.');
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
            'domain_read_credential' => 'domainProcessId',
            'vault_read_credential' => 'applicationProcessId',
            'shared_registration_new_to_encrypt' => 'registrationProcessId',
            'shared_registration_new' => 'registrationProcessId',
            'domain_delete_credential' => 'removeProcessId',         
            'vault_delete_credential' => 'removeProcessId',       
            'vault_edit_credential' => 'registrationProcessId',
            'user_registration' => 'registrationProcessId',
            'domain_read_credential_encrypted' => 'domainProcessId',
            'vault_read_credential_encrypted' => 'applicationProcessId',
            'one_touch_identifier' => 'oneTouchProcessId',  
            default => null,
        };
    }
}
