<?php

namespace App\Admin\Studio\Enum;

use App\Shared\Translation\EnumTranslation;

enum GameTaskStatusEnum: string
{
    use EnumTranslation;

    case TODO = 'todo';
    case DOING = 'doing';
    case DONE = 'done';

    public function tone(): string
    {
        return match ($this) {
            self::TODO => 'accent',
            self::DOING => 'warn',
            self::DONE => 'ok',
        };
    }
}
