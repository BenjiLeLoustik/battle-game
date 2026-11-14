<?php

namespace App\Shared\Game\Exception;

use App\Shared\Exception\BattleGameException;

class GameActionException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.game.forbidden_action';
    protected const ERROR_STATUS = 403;
}