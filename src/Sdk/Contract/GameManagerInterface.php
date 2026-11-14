<?php

namespace App\Sdk\Contract;

interface GameManagerInterface
{
    public function configure(array $settings): array;

    public function init(array $players, array $settings, int $startsAt): array;
}