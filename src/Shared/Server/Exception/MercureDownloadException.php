<?php

namespace App\Shared\Server\Exception;

use App\Shared\Exception\BattleGameException;

class MercureDownloadException extends BattleGameException
{
    protected const ERROR_TRANSLATE_KEY = 'error.server.mercure_download';
    protected const ERROR_STATUS = 502;
}