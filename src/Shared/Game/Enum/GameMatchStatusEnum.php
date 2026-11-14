<?php

namespace App\Shared\Game\Enum;

use App\Shared\Translation\EnumTranslation;

enum GameMatchStatusEnum: string
{
    use EnumTranslation;

    case WAITING = 'waiting';
    case PLAYING = 'playing';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';
}