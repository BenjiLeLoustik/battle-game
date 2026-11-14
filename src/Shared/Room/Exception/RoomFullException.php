<?php

namespace App\Shared\Room\Exception;

use App\Shared\Exception\BattleGameException;

class RoomFullException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.room.full';
    protected const ERROR_STATUS = 409;
}
