<?php

namespace App\Shared\Shop\Enum;

use App\Shared\Translation\EnumTranslation;

enum InvoiceKindEnum: string
{
    use EnumTranslation;

    case INVOICE = 'invoice';
    case CREDIT = 'credit';

    public function prefix(): string
    {
        return $this === self::INVOICE ? 'FAC' : 'AV';
    }
}
