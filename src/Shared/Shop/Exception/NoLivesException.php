<?php

namespace App\Shared\Shop\Exception;

use App\Shared\Exception\BattleGameException;

class NoLivesException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.shop.lives';
    protected const ERROR_STATUS = 403;
}
