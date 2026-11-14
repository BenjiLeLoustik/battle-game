<?php

namespace App\Shared\Maintenance\Enum;

use App\Shared\Translation\EnumTranslation;

enum MaintenanceStatusEnum: string
{
    use EnumTranslation;

    case PLANNED = 'planned';
    case RUNNING = 'running';
    case DONE = 'done';
    case CANCELLED = 'cancelled';
}
