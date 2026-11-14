<?php

namespace App\Shared\Game\Exception;

use App\Shared\Exception\BattleGameException;

class GameManifestException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.game.invalid_manifest';
    protected const ERROR_STATUS = 422;
}