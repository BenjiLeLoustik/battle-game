<?php

namespace App\Shared\Shop\Enum;

use App\Shared\Translation\EnumTranslation;

enum ShopProductTypeEnum: string
{
    use EnumTranslation;

    case COINS = 'coins';
    case SUBSCRIPTION = 'subscription';
    case BADGE = 'badge';
    case COSMETIC = 'cosmetic';
    case PHYSICAL = 'physical';
    case LIVES = 'lives';
    case GIFT = 'gift';

    public function isDigital(): bool
    {
        return $this !== self::PHYSICAL;
    }

    public function allowsCoins(): bool
    {
        return !in_array($this, [self::COINS, self::SUBSCRIPTION, self::PHYSICAL, self::GIFT], true);
    }
}
