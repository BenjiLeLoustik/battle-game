<?php

namespace App\Shared\Leaderboard\Enum;

use App\Shared\Translation\EnumTranslation;

enum LeaderboardPeriodEnum: string
{
    use EnumTranslation;

    case WEEK = 'week';
    case MONTH = 'month';
    case SEASON = 'season';
    case ALL = 'all';
}