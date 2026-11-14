<?php

namespace App\Shared\Exception;

use App\Shared\Translation\ExceptionTranslation;
use NeoPHP\Component\Exception\Exception\FrameworkException;
use Throwable;

class BattleGameException extends FrameworkException
{
    protected const ERROR_TRANSLATE_KEY = 'error.unknown';
    protected const ERROR_STATUS = 500;

    public function __construct(array $parameters = [], ?Throwable $previous = null)
    {
        parent::__construct(static::ERROR_TRANSLATE_KEY, 0, $previous, $parameters);
        $this->setStatusCode(static::ERROR_STATUS);
    }

    public function getTranslationKey(): string
    {
        return static::ERROR_TRANSLATE_KEY;
    }

    public function translate(): string
    {
        return ExceptionTranslation::translate($this);
    }
}