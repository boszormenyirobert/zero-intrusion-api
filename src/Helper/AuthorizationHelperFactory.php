<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class AuthorizationHelperFactory
{
    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function create(): AuthorizationHelper
    {
        return new AuthorizationHelper(
            (string) $this->params->get('SERVICE_API_KEY'),
            (string) $this->params->get('SERVICE_API_SECRET'),
            $this->logger,
        );
    }
}