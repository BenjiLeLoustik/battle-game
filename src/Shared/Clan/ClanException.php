<?php

namespace App\Shared\Clan;

use App\Shared\Exception\BattleGameException;

class ClanException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.clan.invalid';
    protected const ERROR_STATUS = 400;
}
