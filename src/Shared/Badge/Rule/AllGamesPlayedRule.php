<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class AllGamesPlayedRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'all_games_played';
    }

    public function getParameters(): array
    {
        return [];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        return [$stats->distinctGames($user), max(1, $stats->liveGames())];
    }
}
