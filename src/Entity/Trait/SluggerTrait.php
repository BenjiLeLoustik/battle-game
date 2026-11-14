<?php

namespace App\Entity\Trait;

use NeoPHP\Package\Orm\Mapping as ORM;

trait SluggerTrait
{
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $slug;

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $value): static
    {
        $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
        $this->slug = trim(preg_replace('/[^a-z0-9]+/', '-', $value), '-');

        return $this;
    }
}