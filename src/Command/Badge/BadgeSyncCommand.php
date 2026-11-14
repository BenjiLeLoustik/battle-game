<?php

namespace App\Command\Badge;

use App\Repository\UserRepository;
use App\Shared\Badge\BadgeManager;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;
use NeoPHP\Process\Console\IO\InputOption;

#[AsCommand(name: 'battlegame:badge:sync', description: 'Creates or updates the general badges and the badges declared by the games')]
class BadgeSyncCommand extends AbstractConsole
{
    public function __construct(
        protected BadgeManager $badges,
        protected UserRepository $users
    ) {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addOption('award', null, InputOption::VALUE_NONE, 'Awards the badges to the players who already meet the conditions');
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->badges->sync();
        $output->success(sprintf('%d badge(s) synchronized.', $count));

        if ($input->getOption('award')) {
            $awarded = 0;

            foreach ($this->users->findAll() as $user) {
                $awarded += count($this->badges->evaluate($user, $this->badges->playedGames($user)));
            }

            $output->success(sprintf('%d badge(s) awarded.', $awarded));
        }

        return self::SUCCESS;
    }
}
