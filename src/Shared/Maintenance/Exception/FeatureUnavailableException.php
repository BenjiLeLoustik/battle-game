<?php

namespace App\Shared\Maintenance\Exception;

use App\Shared\Exception\BattleGameException;

class FeatureUnavailableException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.maintenance.feature';
    protected const ERROR_STATUS = 503;
}
