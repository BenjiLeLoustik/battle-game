<?php

namespace App\Shared\Shop\Enum;

use App\Shared\Translation\EnumTranslation;

enum ShopPaymentEnum: string
{
    use EnumTranslation;

    case CARD = 'card';
    case COINS = 'coins';
}
