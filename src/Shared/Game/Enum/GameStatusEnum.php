<?php

namespace App\Shared\Game\Enum;

use App\Shared\Translation\EnumTranslation;

enum GameStatusEnum: string
{
    use EnumTranslation;

    case IDEA = 'idea';
    case DEV = 'dev';
    case TEST = 'test';
    case LIVE = 'live';
    case MAINTENANCE = 'maintenance';
    case ARCHIVED = 'archived';
}
