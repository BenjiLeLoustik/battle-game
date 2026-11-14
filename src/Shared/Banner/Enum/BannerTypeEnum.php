<?php

namespace App\Shared\Banner\Enum;

use App\Shared\Translation\EnumTranslation;

enum BannerTypeEnum: string
{
    use EnumTranslation;

    case INFO = 'info';
    case EVENT = 'event';
    case NEWS = 'news';
    case WARNING = 'warning';
}
