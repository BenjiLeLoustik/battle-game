<?php

namespace App\Shared\Shop\Enum;

use App\Shared\Translation\EnumTranslation;

enum CosmeticKindEnum: string
{
    use EnumTranslation;

    case FRAME = 'frame';
    case COLOR = 'color';
    case TITLE = 'title';
    case BANNER = 'banner';
    case VICTORY = 'victory';
}
