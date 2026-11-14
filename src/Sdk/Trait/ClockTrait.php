<?php

namespace App\Sdk\Trait;

trait ClockTrait
{
    protected function now(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}