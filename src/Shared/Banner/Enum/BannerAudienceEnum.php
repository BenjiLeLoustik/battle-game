<?php

namespace App\Shared\Banner\Enum;

use App\Shared\Translation\EnumTranslation;

enum BannerAudienceEnum: string
{
    use EnumTranslation;

    case ALL = 'all';
    case MEMBERS = 'members';
    case GUESTS = 'guests';
}
