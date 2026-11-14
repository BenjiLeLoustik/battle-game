<?php

namespace App\Admin\Studio\Enum;

use App\Shared\Translation\EnumTranslation;

enum GameReviewStatusEnum: string
{
    use EnumTranslation;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function tone(): string
    {
        return match ($this) {
            self::PENDING => 'warn',
            self::APPROVED => 'ok',
            self::REJECTED => 'danger',
        };
    }
}
