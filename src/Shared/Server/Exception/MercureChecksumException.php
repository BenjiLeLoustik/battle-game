<?php

namespace App\Shared\Server\Exception;

use App\Shared\Exception\BattleGameException;

class MercureChecksumException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.server.mercure_checksum';
    protected const ERROR_STATUS = 500;
}