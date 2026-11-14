<?php

namespace App\Shared\Translation;

use App\Shared\Exception\BattleGameException;
use NeoPHP\Component\Exception\Contract\AbstractException;
use Throwable;

class ExceptionTranslation
{
    public static function translate(Throwable $exception): string
    {
        if ($exception instanceof BattleGameException) {
            return Translator::trans($exception->getTranslationKey(), $exception->getContext(), 'errors');
        }

        $key = 'error.html.' . self::status($exception);
        $message = Translator::trans($key, [], 'errors');

        return $message === $key
            ? Translator::trans('error.http.default', [], 'errors')
            : $message;
    }

    public static function status(Throwable $exception): int
    {
        return $exception instanceof AbstractException
            ? $exception->getStatusCode()
            : 500;
    }
}