<?php

namespace App\Shared\Social\Enum;

use App\Shared\Translation\EnumTranslation;

enum ReportStatusEnum: string
{
    use EnumTranslation;

    case OPEN = 'open';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';
}
