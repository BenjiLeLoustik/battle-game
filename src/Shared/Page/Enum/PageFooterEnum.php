<?php

namespace App\Shared\Page\Enum;

use App\Shared\Translation\EnumTranslation;

enum PageFooterEnum: string
{
    use EnumTranslation;

    case NONE = 'none';
    case PLAY = 'play';
    case DEVELOPERS = 'developers';
    case INFO = 'info';
}
