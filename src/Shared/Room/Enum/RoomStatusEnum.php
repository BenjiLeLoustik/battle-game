<?php

namespace App\Shared\Room\Enum;

use App\Shared\Translation\EnumTranslation;

enum RoomStatusEnum: string
{
    use EnumTranslation;

    case WAITING = 'waiting';
    case VOTING = 'voting';
    case CONFIGURING = 'configuring';
    case LAUNCHING = 'launching';
    case PLAYING = 'playing';
}
