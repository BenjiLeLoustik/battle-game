<?php

namespace App\Command\User;

use App\Repository\UserRepository;
use DateTime;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;
use NeoPHP\Process\Console\IO\InputOption;

#[AsCommand(name: 'battlegame:user:purge-guests', description: 'Deletes the guest accounts inactive for a number of days')]
class UserPurgeGuestsCommand extends AbstractConsole
{
    public function __construct(protected UserRepository $users)
    {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addOption('days', null, InputOption::VALUE_REQUIRED, 'Days of inactivity before deletion', '30');
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $days = max(1, (int) $input->getOption('days'));
        $deleted = $this->users->purgeGuests(new DateTime('-' . $days . ' days'));

        $output->success(sprintf('%d guest account(s) inactive for %d days deleted.', $deleted, $days));

        return self::SUCCESS;
    }
}
