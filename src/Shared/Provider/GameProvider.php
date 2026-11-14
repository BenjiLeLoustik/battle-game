<?php

namespace App\Shared\Provider;

use App\Shared\Game\GameAutoloader;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\ContainerManagerInterface;
use NeoPHP\Component\Container\Contract\AbstractProvider;

class GameProvider extends AbstractProvider
{
    public function register(ContainerManagerInterface $container): void
    {
        (new GameAutoloader(dirname(__DIR__, 3) . '/' . GameManager::GAMES_DIRECTORY))->register();
    }

    public function boot(ContainerManagerInterface $container): void
    {
    }
}