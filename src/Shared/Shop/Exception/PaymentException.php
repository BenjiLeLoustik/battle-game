<?php

namespace App\Shared\Shop\Exception;

use App\Shared\Exception\BattleGameException;

class PaymentException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.shop.payment';
    protected const ERROR_STATUS = 502;
}
