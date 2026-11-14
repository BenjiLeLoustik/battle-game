<?php

namespace App\Shared\Shop\Enum;

use App\Shared\Translation\EnumTranslation;

enum ShopOrderStatusEnum: string
{
    use EnumTranslation;

    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warn',
            self::PAID, self::SHIPPED => 'ok',
            self::CANCELLED, self::REFUNDED => 'danger',
        };
    }
}
