<?php

declare(strict_types=1);

namespace App\Service\Request;

use App\Helper\UtilityHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;

class RequestHmacAuthorizationValidator
{
    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger
    ) {
    }

    public function validate(Request $request, array $payload): array
    {
        try {
            return $this->validateOrFail($request, $payload);
        } catch (\InvalidArgumentException $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    public function validateOrFail(Request $request, array $payload): array
    {
        $matches = UtilityHelper::validateAuthHeaderFormat((string) $request->headers->get('X-Auth', ''));

        if (array_key_exists('error', $matches)) {
            $this->logger->critical('Autheader matches: ' . json_encode((array) $matches));

            throw new \InvalidArgumentException((string) $matches['error']);
        }

        $this->logger->info('RequestService validateAuthHeader format validated.', [
            'payload_keys' => array_keys($payload),
        ]);

        $validatedExpectation = UtilityHelper::compareExpectations(
            $matches,
            $this->params,
            (string) $payload['zeroIntrusionProyApi'],
            (string) ($payload['iv'] ?? '')
        );

        if ($validatedExpectation['error'] !== false) {
            $this->logger->critical('Validated expected key: ' . json_encode($validatedExpectation));

            throw new \InvalidArgumentException((string) $validatedExpectation['error']);
        }

        $this->logger->info('RequestService validateAuthHeader HMAC validation succeeded.', [
            'payload_keys' => array_keys($payload),
        ]);

        return $payload;
    }
}