<?php

namespace App\Shared\Shop\Exception;

use App\Shared\Exception\BattleGameException;

class SubscriptionGiftException extends BattleGameException
{
    protected const ERROR_STATUS = 400;

    public function __construct(private string $translationKey)
    {
        parent::__construct();
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}