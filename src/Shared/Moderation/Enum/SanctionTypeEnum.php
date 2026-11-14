<?php

namespace App\Shared\Moderation\Enum;

use App\Shared\Translation\EnumTranslation;

enum SanctionTypeEnum: string
{
    use EnumTranslation;

    case WARNING = 'warning';
    case MUTE = 'mute';
    case SUSPENSION = 'suspension';
    case BAN = 'ban';
}
