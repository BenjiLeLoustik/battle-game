<?php

namespace App\Admin\Studio\Exception;

use App\Shared\Exception\BattleGameException;

class StudioFileException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.studio.invalid_file';
    protected const ERROR_STATUS = 400;
}