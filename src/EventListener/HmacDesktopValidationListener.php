<?php

namespace App\EventListener;

use App\Attribute\DesktopHmac;
use App\Repository\AuthBridgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\Crypters\CrypterService;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Repository\CorporateIdentityRepository;
use App\Service\Crypters\CrypterDatabaseService;

class HmacDesktopValidationListener
{
    public function __construct(
        private readonly CrypterService $crypterService,
        private LoggerInterface $logger,
        private AuthBridgeRepository $authBridgeRepository,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $params,
        private CorporateIdentityRepository $corporateIdentityRepository,
        private CrypterDatabaseService $crypterDatabaseService
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {

    $request = $event->getRequest();
    $controllerString = $request->attributes->get('_controller');

    if (!is_string($controllerString) || !str_contains($controllerString, '::')) {
        $this->logger->critical('Invalid _controller format');
        return;
    }

    [$controllerClass, $method] = explode('::', $controllerString, 2);

    $reflection = new \ReflectionMethod($controllerClass, $method);
    $hasHmacCheck = !empty(
        $reflection->getAttributes(\App\Attribute\DesktopHmac::class)
    );

    if (!$hasHmacCheck) {
        $this->logger->critical('Return before use HmacDesktopValidationListener');
        return;
    }
        try{
            $request = $event->getRequest();
            $authHeader = $request->headers->get('X-Extension-Auth');
            $payloadKey = $request->attributes->get('_route'); // Use route name as payload key

            $payload = json_decode($request->getContent(), true);
        } catch (\Throwable $e) {
            $this->logger->critical('Exception during request processing: ' . $e->getMessage());
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Exception during request processing',
            ], 400));
            $event->stopPropagation();;
            return;
        }

        if (!is_array($payload)) {
            $this->logger->critical('Invalid JSON body');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], 400));
            $event->stopPropagation();;return;
        }

        if (!isset($payload['iv'], $payload['zeroIntrusionProyApi'])) {
            $this->logger->critical('Missing required fields');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400));
            $event->stopPropagation();;return;
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
            $event->stopPropagation();;return;
        }
        $innerJson = $data[$payloadKey] ?? null;

        if ($innerJson) {
            $payload = is_string($innerJson) ? json_decode($innerJson, true) : $innerJson;

            if (!is_array($payload)) {
                $this->logger->critical('Decoded innerJson is not array.');
                $event->setController(fn() => new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid inner payload',
                ], 400));
                $event->stopPropagation();
                return;
            }

            $payloadDecoded = $payload;
        } else {
            $this->logger->critical('payloadKey missing or null');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'payloadKey missing or null',
            ], 400));
            $event->stopPropagation();
            return;
        }
// Get all headers
$allHeaders = $request->headers->all();

// Convert to string for logging
$headerLog = '';
foreach ($allHeaders as $name => $values) {
    $headerLog .= $name . ': ' . implode(', ', $values) . "\n";
}

$this->logger->critical("Incoming request headers:\n" . $headerLog);


        $recvSignature = strtolower($request->headers->get('x-extension-auth'));
                $this->logger->critical('recvSignature: ' . $recvSignature);

        $corporateId = $payloadDecoded['publicId'] ?? null;
        $message = $payloadDecoded['message'] ?? null;
        $domain = $payloadDecoded['domain'] ?? null;        
        $timestamp = $payloadDecoded['timestamp'] ?? null;

        $this->logger->critical('corporateId: ' . $corporateId);
        $this->logger->critical('message: ' . $message);
        $this->logger->critical('domain: ' . $domain);      
        $this->logger->critical('hmac: ' . $recvSignature);
        $this->logger->critical('timestamp: ' . $timestamp);

        
        // $expectedSecret => CorporateIdSecret from DB
        $corporateDbEncrypted = $this->corporateIdentityRepository->findOneBy(['corporateId' => $corporateId]);
        $corporate = $this->crypterDatabaseService->decryptFromDatabase($corporateDbEncrypted);

        $this->logger->critical('Decrypted CorporateIdSecret: ' . $corporate->getCorporateIdSecret());

        $expectedSecret = $corporate->getCorporateIdSecret();
        $expectedCorporateIdKey = $corporate->getCorporateIdKey();

        $controllMessage = $expectedCorporateIdKey . '|' . $timestamp;

            $expectedSignature = hash_hmac('sha256', $controllMessage, $expectedSecret);

            if (!hash_equals($expectedSignature, $recvSignature)) {
                $this->logger->critical('Invalid HMAC signature');
                throw new InvalidHmacException('Invalid HMAC signature');
            }
    
        // CorporateIdSecret used for HMAC validation

        if (!$authHeader || (!$processId && $payloadKey !== 'api_nfc_users') ) {
            $this->logger->critical('Missing HMAC header or process ID.');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing HMAC header or process ID.',
            ], 403));
            $event->stopPropagation();;return;
        }

        $processKey =  $this->resolveProcessKey($payloadKey);

        if (!$processKey) {
            $this->logger->critical("Unknown payload type: $payloadKey");
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => "Unknown payload type: $payloadKey",
            ], 400));
            $event->stopPropagation();;return;
        }

        if($processKey !== 'api_nfc_users'){
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
        
        if($processKey !== 'api_nfc_users'){
            $this->logger->critical('MISSING HMAC DESKTOP HMAC VALIDATION.');
        }
        $this->logger->critical('Stop HmacExtensionValidationListener');
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
            'api_nfc_users' => 'api_nfc_users',
            default => null,
        };
    }
}
