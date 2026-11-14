<?php

namespace App\Shared\Room\Exception;

use App\Shared\Exception\BattleGameException;

class RoomActionException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.room.forbidden_action';
    protected const ERROR_STATUS = 403;
}
