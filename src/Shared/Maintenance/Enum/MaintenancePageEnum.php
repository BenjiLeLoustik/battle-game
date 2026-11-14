<?php

namespace App\Shared\Maintenance\Enum;

use App\Shared\Translation\EnumTranslation;

enum MaintenancePageEnum: string
{
    use EnumTranslation;

    case LEADERBOARD = 'leaderboard';
    case GAMES = 'games';

    public function path(): string
    {
        return match ($this) {
            self::LEADERBOARD => '/leaderboard',
            self::GAMES => '/games',
        };
    }
}
