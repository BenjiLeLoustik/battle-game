<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class WinStreakRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'win_streak';
    }

    public function getParameters(): array
    {
        return ['streak' => 'int'];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        return [$stats->streak($user, $game), (int) ($params['streak'] ?? 2)];
    }
}
