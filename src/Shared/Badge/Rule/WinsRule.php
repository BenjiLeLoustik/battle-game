<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class WinsRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'wins';
    }

    public function getParameters(): array
    {
        return ['count' => 'int'];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        return [$stats->wins($user, $game), (int) ($params['count'] ?? 1)];
    }
}
