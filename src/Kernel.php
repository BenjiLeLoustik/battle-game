<?php

declare(strict_types=1);

namespace App;

use App\Shared\Provider\GameProvider;
use NeoPHP\Component\Kernel\Contract\AbstractKernel;
use App\Shared\Provider\TranslationProvider;

class Kernel extends AbstractKernel
{
    public const VERSION = '0.1.0';

    protected function providers(): iterable
    {
        return [
            TranslationProvider::class,
            GameProvider::class,
        ];
    }
}