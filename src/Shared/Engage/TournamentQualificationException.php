<?php

namespace App\Shared\Engage;

use App\Shared\Exception\BattleGameException;

class TournamentQualificationException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.tournament.not_qualified';
    protected const ERROR_STATUS = 403;
}
