<?php

namespace App\Command\Game;

use App\Shared\Game\Exception\GameManifestException;
use App\Shared\Game\GameManager;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;
use NeoPHP\Process\Console\IO\InputArgument;

#[AsCommand(name: 'battlegame:game:install', description: 'Installs (or updates) a game stored in var/games/')]
class GameInstallCommand extends AbstractConsole
{
    public function __construct(
        protected GameManager $games,
    ) {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addArgument(
            'directory',
            InputArgument::REQUIRED,
            'Directory of the game in var/games/',
            null,
            'Directory of the game in var/games/ (e.g. PulseDuel)'
        );
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        try {
            $game = $this->games->install((string) $input->getArgument('directory'));
        } catch (GameManifestException $exception) {
            $output->error('Invalid game: ' . $exception->getContext()['reason'] . '.');

            return self::FAILURE;
        }

        $output->success(
            sprintf('%s %s is installed (slug: %s).', $game->getName(), $game->getVersion(), $game->getSlug())
        );

        return self::SUCCESS;
    }
}