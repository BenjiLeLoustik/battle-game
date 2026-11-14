<?php

namespace App\Shared\Engage;

use App\Shared\Exception\BattleGameException;

class TournamentException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.tournament.invalid';
    protected const ERROR_STATUS = 400;
}
