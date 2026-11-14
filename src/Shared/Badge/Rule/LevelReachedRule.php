<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class LevelReachedRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'level_reached';
    }

    public function getParameters(): array
    {
        return ['level' => 'int'];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        return [$stats->level($user, $game), (int) ($params['level'] ?? 1)];
    }
}
