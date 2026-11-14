<?php

namespace App\Shared\Social\Enum;

use App\Shared\Translation\EnumTranslation;

enum ChatAccessEnum: string
{
    use EnumTranslation;

    case EVERYONE = 'everyone';
    case MEMBERS = 'members';
    case STAFF = 'staff';
    case NOBODY = 'nobody';

    public static function readable(): array
    {
        return [self::EVERYONE, self::MEMBERS, self::STAFF];
    }

    public static function writable(): array
    {
        return [self::MEMBERS, self::STAFF, self::NOBODY];
    }
}
