<?php

namespace App\Shared\Shop\Enum;

use App\Shared\Translation\EnumTranslation;

enum VoucherKindEnum: string
{
    use EnumTranslation;

    case PROMO = 'promo';
    case GIFT = 'gift';
}
