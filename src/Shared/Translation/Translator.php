<?php

namespace App\Shared\Translation;

use NeoPHP\Package\Translation\TranslationManagerInterface;

class Translator
{
    private static ?TranslationManagerInterface $manager = null;

    public static function setManager(TranslationManagerInterface $manager): void
    {
        self::$manager = $manager;
    }

    public static function trans(string $key, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return self::$manager?->translate($key, $parameters, $domain, $locale) ?? $key;
    }
}