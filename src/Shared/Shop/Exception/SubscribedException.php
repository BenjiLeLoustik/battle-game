<?php

namespace App\Shared\Shop\Exception;

use App\Shared\Exception\BattleGameException;

class SubscribedException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.shop.subscribed';
    protected const ERROR_STATUS = 409;
}
