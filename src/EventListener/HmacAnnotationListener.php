<?php

namespace App\EventListener;

use App\Attribute\RequireHmac;
use App\Service\Hmac\HmacValidator;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Psr\Log\LoggerInterface;

#[AsEventListener]
class HmacAnnotationListener
{
    public function __construct(
        private HmacValidator $validator,
        private LoggerInterface $logger
    ) {}

    public function __invoke(ControllerEvent $event): void
    {

        $controller = $event->getController();
        if (!is_array($controller)) return;

        [$object, $method] = $controller;
        $refMethod = new ReflectionMethod($object, $method);

        if (!empty($refMethod->getAttributes(RequireHmac::class))) {
            $request = $event->getRequest();
            $payload = json_decode($request->getContent(), true);

            if (!is_array($payload)) {
                $this->logger->critical('Invalid JSON body');
                throw new BadRequestHttpException('Invalid JSON body');
            }

            if (!isset($payload['iv'], $payload['zeroIntrusionProyApi'])) {
                $this->logger->critical('Missing required fields');
                throw new BadRequestHttpException('Missing required fields');
            }

            $authHeader = $request->headers->get('X-Auth');

            $validation = $this->validator->validate(
                $request->getContent(),
                $authHeader,
                $payload['iv'],
                $payload
            );

            if (!$validation) {
                throw new BadRequestHttpException('HMAC validation failed');
            }
        }
    }
}
