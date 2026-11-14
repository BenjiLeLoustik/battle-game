<?php

namespace App\Command\User;

use App\Repository\AdminRoleRepository;
use App\Repository\UserRepository;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;
use NeoPHP\Process\Console\IO\InputArgument;

#[AsCommand(name: 'battlegame:user:promote', description: 'Gives the administrator role to a user')]
class UserPromoteCommand extends AbstractConsole
{
    public function __construct(
        protected UserRepository $users,
        protected AdminRoleRepository $roles
    ) {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addArgument(
            'email',
            InputArgument::REQUIRED,
            'Email of the user',
            null,
            'Email of the user to promote'
        );
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->users->findOneBy([
            'email' => mb_strtolower((string) $input->getArgument('email')),
        ]);

        if ($user === null) {
            $output->error('No user found with this email.');

            return self::FAILURE;
        }

        $user->setRoles(array_values(array_unique([...$user->getRoles(), 'ROLE_ADMIN'])))
            ->setAdminRole($this->roles->findOneBy(['system' => true]));
        $this->users->save($user, true);

        $output->success(
            sprintf('%s is now an administrator.', $user->getUsername())
        );

        return self::SUCCESS;
    }
}