<?php

namespace App\Command\Game;

use App\Shared\Game\Exception\GameManifestException;
use App\Shared\Game\GameManager;
use NeoPHP\Component\Container\Attribute\Autowire;
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
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
    ) {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            'Directory of the game in var/games',
        );
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');
        $directories = $directory !== null && $directory !== ''
            ? [(string) $directory]
            : array_map(
                fn (string $manifest): string => basename(dirname($manifest)),
                glob($this->rootPath . '/' . GameManager::GAMES_DIRECTORY . '/*/game.json') ?: [],
            );

        if ($directories === []) {
            $output->error('No games found in var/games');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($directories as $directory) {
            try {
                $game = $this->games->install($directory);
                $output->success(sprintf(
                    '%s %s is installed (slug %s).',
                    $game->getName(),
                    $game->getVersion(),
                    $game->getSlug(),
                ));
            } catch (GameManifestException $exception) {
                $output->error(sprintf(
                    'Invalid game in %s : %s',
                    $directory,
                    $exception->getContext()['reason']
                ));

                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}