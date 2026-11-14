<?php

namespace App\Command\Server;

use App\Shared\Exception\BattleGameException;
use App\Shared\Server\MercureHubManager;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;

#[AsCommand(name: 'battlegame:server:install', description: 'Downloads the Mercure hub into var/mercure/')]
class ServerInstallCommand extends AbstractConsole
{
    public function __construct(
        protected MercureHubManager $hub,
    ) {
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        if ($this->hub->isInstalled() && !$input->getOption('force')) {
            $output->success('The Mercure hub is already installed: ' . $this->hub->getBinary());

            return self::SUCCESS;
        }

        try {
            $this->hub->install();
        } catch (BattleGameException $exception) {
            $output->error('The Mercure hub could not be installed (' . $exception->getTranslationKey() . ')');

            return self::FAILURE;
        }

        $output->success('Mercure hub v' . MercureHubManager::VERSION . ' installed: ' . $this->hub->getBinary());
        return self::SUCCESS;
    }
}