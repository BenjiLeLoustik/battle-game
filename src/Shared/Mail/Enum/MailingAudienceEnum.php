<?php

namespace App\Shared\Mail\Enum;

use App\Shared\Translation\EnumTranslation;

enum MailingAudienceEnum: string
{
    use EnumTranslation;

    case ALL = 'all';
    case ADMINS = 'admins';
    case COUNTRY = 'country';
    case GAME = 'game';
}
