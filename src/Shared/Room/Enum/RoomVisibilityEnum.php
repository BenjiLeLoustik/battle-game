<?php

namespace App\Shared\Room\Enum;

use App\Shared\Translation\EnumTranslation;

enum RoomVisibilityEnum: string
{
    use EnumTranslation;

    case CODE = 'code';
    case PASSWORD = 'password';
    case PUBLIC = 'public';
}
