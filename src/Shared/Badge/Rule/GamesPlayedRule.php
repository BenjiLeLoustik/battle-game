<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class GamesPlayedRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'games_played';
    }

    public function getParameters(): array
    {
        return ['count' => 'int'];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        return [$stats->played($user, $game), (int) ($params['count'] ?? 1)];
    }
}
