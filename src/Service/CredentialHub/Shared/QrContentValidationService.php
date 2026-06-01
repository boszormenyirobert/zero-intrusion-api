<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class QrContentValidationService
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function validateOrFail(mixed $qrContent, string $context): void
    {
        $errors = $this->validator->validate($qrContent);

        if (count($errors) === 0) {
            return;
        }

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = sprintf('%s: %s', $error->getPropertyPath(), $error->getMessage());
        }

        $message = sprintf('Invalid %s QR content: %s', $context, implode('; ', array_unique($messages)));
        $this->logger->critical($message);

        throw new \InvalidArgumentException($message);
    }
}
