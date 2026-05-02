<?php

declare(strict_types=1);

namespace App\Controller\PayloadValidator;

use App\Service\Payload\PayloadIntegrityKeyRegistry;
use App\Service\Payload\PayloadValidator as BasePayloadValidator;
use App\Service\Payload\ValidatedPayloadResolver;
use App\Service\Shared\RequestService;
use Psr\Log\LoggerInterface;

class PayloadValidator extends BasePayloadValidator
{
    public function __construct(LoggerInterface $logger, RequestService $requestService)
    {
        parent::__construct(
            $logger,
            new ValidatedPayloadResolver($requestService),
            new PayloadIntegrityKeyRegistry()
        );
    }
}
