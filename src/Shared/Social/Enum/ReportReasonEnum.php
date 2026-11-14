<?php

namespace App\Shared\Social\Enum;

use App\Shared\Translation\EnumTranslation;

enum ReportReasonEnum: string
{
    use EnumTranslation;

    case CHEATING = 'cheating';
    case ABUSE = 'abuse';
    case PROFILE = 'profile';
    case OTHER = 'other';
}
