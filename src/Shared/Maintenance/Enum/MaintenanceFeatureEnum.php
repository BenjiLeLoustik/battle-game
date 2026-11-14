<?php

namespace App\Shared\Maintenance\Enum;

use App\Shared\Translation\EnumTranslation;

enum MaintenanceFeatureEnum: string
{
    use EnumTranslation;

    case LEADERBOARD = 'leaderboard';
    case REGISTRATION = 'registration';
    case DISCORD = 'discord';

    public function paths(): array
    {
        return match ($this) {
            self::LEADERBOARD => ['/leaderboard'],
            self::REGISTRATION => ['/register'],
            self::DISCORD => ['/oauth/discord', '/register/discord']
        };
    }
}
