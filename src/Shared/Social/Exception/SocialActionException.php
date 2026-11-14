<?php

namespace App\Shared\Social\Exception;

use App\Shared\Exception\BattleGameException;

class SocialActionException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.social.forbidden_action';
    protected const ERROR_STATUS = 403;
}
