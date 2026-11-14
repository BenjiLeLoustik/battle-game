<?php

namespace App\Shared\Shop;

use App\Repository\GameResultRepository;
use App\Repository\SeasonRepository;
use App\Repository\UserRepository;
use App\Shared\Setting\SettingManager;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;
use NeoPHP\Package\Scheduler\Attribute\AsScheduledTask;

#[AsScheduledTask(cron: '15 * * * *', name: 'battlegame-season-rewards', timezone: 'Europe/Paris', withoutOverlapping: true, description: 'Récompenses en pièces de fin de saison')]
class SeasonRewardTask
{
    public function __invoke(SeasonRepository $seasons, GameResultRepository $results, UserRepository $users, WalletManager $wallets, NotificationManager $notifications, SettingManager $settings): string
    {
        $rewards = array_values(array_filter(array_map('intval', explode(',', (string) $settings->get('season_rewards'))), fn (int $value): bool => $value > 0));
        $done = [];

        foreach ($seasons->findBy(['rewardedAt' => null]) as $season) {
            if ($season->getEndsAt() > new DateTime()) {
                continue;
            }

            $seasons->save($season->setRewardedAt(new DateTime()), true);

            foreach (array_slice($results->ranking(null, $season->getStartsAt(), $season->getEndsAt()), 0, count($rewards)) as $index => $row) {
                $user = $users->find((int) $row['id']);

                if ($user === null || $user->isGuest()) {
                    continue;
                }

                $wallets->credit($user, $rewards[$index], 'season', $season->getName() . ' · #' . ($index + 1));
                $notifications->notify($user, NotificationTypeEnum::SEASON_REWARD, ['season' => $season->getName(), 'rank' => $index + 1, 'coins' => $rewards[$index]]);
            }

            $done[] = $season->getName();
        }

        return $done === [] ? 'nothing to reward' : implode(', ', $done);
    }
}
