<?php

namespace App\Command\Season;

use App\Entity\Season;
use App\Repository\SeasonRepository;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;
use NeoPHP\Process\Console\IO\InputOption;

#[AsCommand(name: 'battlegame:season:create', description: 'Creates a leaderboard season')]
class SeasonCreateCommand extends AbstractConsole
{
    public function __construct(protected SeasonRepository $seasons, protected NotificationManager $notifications)
    {
    }

    protected function configure(InputInterface $input, OutputInterface $output): void
    {
        $input->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Name of the season',
            null,
            'Name of the season (e.g. Season 1)'
        );

        $input->addOption(
            'starts',
            null,
            InputOption::VALUE_REQUIRED,
            'First day (Y-m-d)',
            null,
            'First day of the season (Y-m-d)'
        );

        $input->addOption(
            'ends',
            null,
            InputOption::VALUE_REQUIRED,
            'Last day (Y-m-d)',
            null,
            'Last day of the season (Y-m-d)'
        );
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        $startsAt = DateTime::createFromFormat('!Y-m-d', (string) $input->getOption('starts'));
        $endsAt = DateTime::createFromFormat('!Y-m-d', (string) $input->getOption('ends'));

        if ($startsAt === false || $endsAt === false || $endsAt < $startsAt) {
            $output->error('The dates must use the Y-m-d format and the season must end after it starts.');

            return self::FAILURE;
        }

        $season = (new Season())->setName((string) $input->getOption('name'))
            ->setStartsAt($startsAt)
            ->setEndsAt($endsAt->modify('+1 day'));

        $this->seasons->save($season, true);
        $this->notifications->broadcast(NotificationTypeEnum::SEASON_STARTED, ['season' => $season->getName()]);

        $output->success(
            sprintf(
                '%s is created: %s to %s.',
                $season->getName(),
                $startsAt->format('Y-m-d'),
                $input->getOption('ends')
            )
        );

        return self::SUCCESS;
    }
}