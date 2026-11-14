<?php

namespace App\Shared\Clan\Enum;

use App\Shared\Translation\EnumTranslation;

enum ClanRoleEnum: string
{
    use EnumTranslation;

    case OWNER = 'owner';
    case OFFICER = 'officer';
    case MEMBER = 'member';

    public function canManage(): bool
    {
        return $this !== self::MEMBER;
    }
}
