<?php

namespace App\Shared\Banner\Enum;

use App\Shared\Translation\EnumTranslation;

enum BannerPagesEnum: string
{
    use EnumTranslation;

    case ALL = 'all';
    case HOME = 'home';
    case GAMES = 'games';
}
