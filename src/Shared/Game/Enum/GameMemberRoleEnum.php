<?php

namespace App\Shared\Game\Enum;

use App\Shared\Translation\EnumTranslation;

enum GameMemberRoleEnum: string
{
    use EnumTranslation;

    case LEAD = 'lead';
    case DEVELOPER = 'developer';
    case TESTER = 'tester';
}
