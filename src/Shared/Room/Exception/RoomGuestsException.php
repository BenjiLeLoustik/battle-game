<?php

namespace App\Shared\Room\Exception;

use App\Shared\Exception\BattleGameException;

class RoomGuestsException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.room.guests';
    protected const ERROR_STATUS = 403;
}
