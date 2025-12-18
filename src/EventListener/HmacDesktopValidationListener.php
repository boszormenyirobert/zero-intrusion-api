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
        // Controll validator use of the Listener
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

        // Controll route name to use as payload key
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

        // Controll payload structure
        if (!is_array($payload)) {
            $this->logger->critical('Invalid JSON body');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid JSON body',
            ], 400));
            $event->stopPropagation();;return;
        }

        // Controll required fields as the request sent by the HUB
        if (!isset($payload['iv'], $payload['zeroIntrusionProyApi'])) {
            $this->logger->critical('Missing required fields');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing required fields',
            ], 400));
            $event->stopPropagation();;return;
        }

        // Decrypt the payload data: basic level decryption between HUB - API
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
        // Access to the inner payload by shared payloadKey, which is the route name
        $innerJson = $data[$payloadKey] ?? null;

        if ($innerJson) {
            $payloadDecoded = is_string($innerJson) ? json_decode($innerJson, true) : $innerJson;

            if (!is_array($payloadDecoded)) {
                $this->logger->critical('Decoded innerJson is not array.');
                $event->setController(fn() => new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid inner payload',
                ], 400));
                $event->stopPropagation();
                return;
            }
        } else {
            $this->logger->critical('payloadKey missing or null');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'payloadKey missing or null',
            ], 400));
            $event->stopPropagation();
            return;
        }

        // Get the HMAC signature from headers created by the Desktop Application
        $recvSignature = strtolower($request->headers->get('x-extension-auth'));

        // Extract fields from the payload
        $corporateId = $payloadDecoded['publicId'] ?? null;
        $timestamp = $payloadDecoded['timestamp'] ?? null;
$this->logger->critical('HMAC DESKTOP VALIDATION LISTENER CALLED for corporateId: ' . $corporateId);
        if (!$corporateId || !$timestamp || !$recvSignature) {
            $this->logger->critical('Missing required HMAC fields');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Missing required HMAC fields',
            ], 400));
            $event->stopPropagation();;return;
        }
        // Get corporate data from database to controll HMAC signature
        $corporateDbEncrypted = $this->corporateIdentityRepository->findOneBy(['corporateId' => $corporateId]);
        $corporate = $this->crypterDatabaseService->decryptFromDatabase($corporateDbEncrypted);

        $expectedSecret = $corporate->getCorporateIdSecret();
        $expectedCorporateIdKey = $corporate->getCorporateIdKey();

        // Recreate HMAC signature
        $controllMessage = $expectedCorporateIdKey . '|' . $timestamp;

        $expectedSignature = hash_hmac('sha256', $controllMessage, $expectedSecret);

        // Controll HMAC signature
        if (!hash_equals($expectedSignature, $recvSignature)) {
            $this->logger->critical('Invalid HMAC signature');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Invalid HMAC signature',
            ], 400));
            $event->stopPropagation();;return;
        }
        // Timestamp controll to avoid replay attacks (5 minutes window)
        $currentTime = time();
        if (abs($currentTime - $timestamp) > 300) {
            $this->logger->critical('Timestamp is outside the allowed window');
            $event->setController(fn() => new JsonResponse([
                'success' => false,
                'error' => 'Timestamp is outside the allowed window',
            ], 400));
            $event->stopPropagation();;return;
        }
    
        $this->logger->critical('Stop HmacExtensionValidationListener');
    }
}
