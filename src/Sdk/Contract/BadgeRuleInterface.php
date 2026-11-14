<?php

namespace App\Sdk\Contract;

use App\Entity\Game;
use App\Entity\User;
use App\Shared\Badge\BadgeStats;

interface BadgeRuleInterface
{
    public function getName(): string;

    public function getParameters(): array;

    public function progress(BadgeStats $stats, User $user, ?Game $game, array $params): array;
}
