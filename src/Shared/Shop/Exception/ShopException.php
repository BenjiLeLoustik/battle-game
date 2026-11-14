<?php

namespace App\Shared\Shop\Exception;

use App\Shared\Exception\BattleGameException;

class ShopException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.shop.unavailable';
    protected const ERROR_STATUS = 400;
}
