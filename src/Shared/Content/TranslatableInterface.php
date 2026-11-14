<?php

namespace App\Shared\Content;

interface TranslatableInterface
{
    public function getId(): ?int;

    public static function getTranslatableFields(): array;
}
