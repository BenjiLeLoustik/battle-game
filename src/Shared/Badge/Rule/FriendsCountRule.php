<?php

namespace App\Shared\Badge\Rule;

use App\Entity\Game;
use App\Entity\User;
use App\Sdk\Contract\BadgeRuleInterface;
use App\Shared\Badge\BadgeStats;

class FriendsCountRule implements BadgeRuleInterface
{
    public function getName(): string
    {
        return 'friends_count';
    }

    public function getParameters(): array
    {
        return ['count' => 'int'];
    }

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array
    {
        return [$stats->friends($user), (int) ($params['count'] ?? 1)];
    }
}
