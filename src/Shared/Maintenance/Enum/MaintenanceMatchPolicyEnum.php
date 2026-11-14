<?php

namespace App\Shared\Maintenance\Enum;

use App\Shared\Translation\EnumTranslation;

enum MaintenanceMatchPolicyEnum: string
{
    use EnumTranslation;

    case FINISH = 'finish';
    case STOP = 'stop';
}
