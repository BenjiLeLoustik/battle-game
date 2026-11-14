<?php

namespace App\Shared\Engage;

use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;
use NeoPHP\Package\Scheduler\Attribute\AsScheduledTask;

#[AsScheduledTask(cron: '20 * * * *', name: 'battlegame-tournament-rewards', timezone: 'Europe/Paris', withoutOverlapping: true, description: 'Récompenses des tournois terminés')]
class TournamentRewardTask
{
    public function __invoke(TournamentRepository $tournaments, TournamentManager $manager, RewardManager $rewards, UserRepository $users, NotificationManager $notifications): string
    {
        $done = [];

        foreach ($tournaments->findBy(['rewardedAt' => null]) as $tournament) {
            if ($tournament->getEndsAt() > new DateTime()) {
                continue;
            }

            $tournaments->save($tournament->setRewardedAt(new DateTime()), true);
            $places = $tournament->getRewardsByPlace();
            $results = [];

            foreach ($manager->standings($tournament, $places === [] ? 0 : max(array_keys($places))) as $index => $row) {
                $place = $index + 1;
                $user = $users->find((int) $row['id']);

                if (!isset($places[$place]) || $user === null || (int) $row['games'] === 0) {
                    continue;
                }

                $labels = array_map(fn (array $reward): string => $rewards->give($user, $reward, $tournament->getName() . ' · #' . $place), $places[$place]);
                $results[] = ['place' => $place, 'user' => $user->getId(), 'username' => $user->getUsername(), 'points' => (int) $row['points'], 'rewards' => $labels, 'manual' => in_array('custom', array_column($places[$place], 'type'), true)];
                $notifications->notify($user, NotificationTypeEnum::TOURNAMENT_REWARD, ['tournament' => $tournament->getName(), 'rank' => $place, 'reward' => implode(' + ', $labels), 'id' => $tournament->getId()]);
            }

            $tournaments->save($tournament->setResults($results), true);
            $done[] = $tournament->getName();
        }

        return $done === [] ? 'nothing to reward' : implode(', ', $done);
    }
}
