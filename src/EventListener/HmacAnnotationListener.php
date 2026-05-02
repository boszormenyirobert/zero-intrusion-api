<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\RequireHmac;
use App\Helper\UtilityHelper;
use App\Service\Hmac\HmacValidator;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsEventListener]
class HmacAnnotationListener
{
    private const INVALID_JSON_BODY_ERROR = 'Invalid JSON body';
    private const MISSING_REQUIRED_FIELDS_ERROR = 'Missing required fields';

    public function __construct(
        private readonly HmacValidator $validator,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        [$object, $method] = $controller;
        $refMethod = new ReflectionMethod($object, $method);

        if (empty($refMethod->getAttributes(RequireHmac::class))) {
            return;
        }

        $request = $event->getRequest();
        $payload = UtilityHelper::validateJsonFormat($request);

        if (isset($payload['error'])) {
            $this->throwBadRequest(self::INVALID_JSON_BODY_ERROR);
        }

        if (!isset($payload['iv'], $payload['zeroIntrusionProyApi'])) {
            $this->throwBadRequest(self::MISSING_REQUIRED_FIELDS_ERROR);
        }

        $validation = $this->validator->validate(
            $request->getContent(),
            $request->headers->get('X-Auth'),
            (string) $payload['iv'],
            $payload
        );

        if (!$validation) {
            throw new BadRequestHttpException('HMAC validation failed');
        }
    }

    private function throwBadRequest(string $message): never
    {
        $this->logger->critical($message);

        throw new BadRequestHttpException($message);
    }
}
