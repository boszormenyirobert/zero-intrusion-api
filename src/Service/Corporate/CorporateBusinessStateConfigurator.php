<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\Entity\BusinessServices;

final class CorporateBusinessStateConfigurator
{
    /** @var array<string, array{pro: bool, plus: bool, basic: bool, biometric: bool, passwordManager: bool}> */
    private const BUSINESS_MODEL_FLAGS = [
        'businessPro' => ['pro' => true, 'plus' => false, 'basic' => false, 'biometric' => false, 'passwordManager' => false],
        'businessPlus' => ['pro' => false, 'plus' => true, 'basic' => false, 'biometric' => false, 'passwordManager' => false],
        'businessBasic' => ['pro' => false, 'plus' => false, 'basic' => true, 'biometric' => false, 'passwordManager' => false],
        'biometric' => ['pro' => false, 'plus' => false, 'basic' => false, 'biometric' => true, 'passwordManager' => false],
        'passwordManager' => ['pro' => false, 'plus' => false, 'basic' => false, 'biometric' => false, 'passwordManager' => true],
    ];

    public function apply(BusinessServices $businessServices, string $businessModel): void
    {
        $flags = self::BUSINESS_MODEL_FLAGS[$businessModel] ?? null;

        if ($flags === null) {
            return;
        }

        $businessServices->setPro($flags['pro']);
        $businessServices->setPlus($flags['plus']);
        $businessServices->setBasic($flags['basic']);
        $businessServices->setBiometric($flags['biometric']);
        $businessServices->setPasswordManager($flags['passwordManager']);
    }
}