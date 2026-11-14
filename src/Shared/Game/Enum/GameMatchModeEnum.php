<?php

namespace App\Shared\Game\Enum;

use App\Shared\Translation\EnumTranslation;

enum GameMatchModeEnum: string
{
    use EnumTranslation;

    case QUICK = 'quick';
    case SOLO = 'solo';
    case ROOM = 'room';
    case RANKED = 'ranked';
}