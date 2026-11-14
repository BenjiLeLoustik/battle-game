<?php

namespace App\Shared\Maintenance\Enum;

use App\Shared\Translation\EnumTranslation;

enum MaintenanceTargetEnum: string
{
    use EnumTranslation;
    case SITE = 'site';
    case GAMES = 'games';
    case FEATURES = 'features';
}
