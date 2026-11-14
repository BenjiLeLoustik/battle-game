<?php

namespace App\Shared\Shop\Exception;

use App\Shared\Exception\BattleGameException;

class EarlyAccessException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.shop.early_access';
    protected const ERROR_STATUS = 403;
}
