<?php

namespace App\Shared\Page\Enum;

use App\Shared\Translation\EnumTranslation;

enum PageFormatEnum: string
{
    use EnumTranslation;

    case MARKDOWN = 'markdown';
    case HTML = 'html';
}
