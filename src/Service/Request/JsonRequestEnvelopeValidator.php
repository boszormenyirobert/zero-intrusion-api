<?php

declare(strict_types=1);

namespace App\Service\Request;

use App\Helper\UtilityHelper;
use Symfony\Component\HttpFoundation\Request;

class JsonRequestEnvelopeValidator
{
    public function validate(Request $request): array
    {
        return UtilityHelper::validateJsonFormat($request);
    }
}