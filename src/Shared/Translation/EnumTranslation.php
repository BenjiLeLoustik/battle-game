<?php

namespace App\Shared\Translation;

trait EnumTranslation
{
    public function translate(): string
    {
        return Translator::trans(self::translationPrefix() . '.' . $this->value, [], 'enums');
    }

    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->value] = $case->translate();
        }

        return $choices;
    }

    private static function translationPrefix(): string
    {
        $name = preg_replace('/Enum$/', '', substr(strrchr(self::class, '\\'), 1));

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
    }
}
