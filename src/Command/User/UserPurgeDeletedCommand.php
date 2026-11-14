<?php

namespace App\Command\User;

use App\Repository\UserRepository;
use App\Site\Account\AccountManager;
use DateTime;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;

#[AsCommand(name: 'battlegame:user:purge-deleted', description: 'Anonymizes the accounts whose deletion delay is over')]
class UserPurgeDeletedCommand extends AbstractConsole
{
    public function __construct(
        protected UserRepository $users,
        protected AccountManager $accounts
    ) {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->users->findDeletionDue(new DateTime('-' . AccountManager::DELETION_DELAY . ' days'));

        foreach ($users as $user) {
            $this->accounts->anonymize($user);
        }

        $output->success(sprintf('%d account(s) deleted.', count($users)));

        return self::SUCCESS;
    }
}
