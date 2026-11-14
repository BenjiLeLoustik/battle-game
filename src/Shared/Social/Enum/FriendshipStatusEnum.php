<?php

namespace App\Shared\Social\Enum;

use App\Shared\Translation\EnumTranslation;

enum FriendshipStatusEnum: string
{
    use EnumTranslation;

    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
}
