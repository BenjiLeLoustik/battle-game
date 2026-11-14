<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\CountryRepository;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: CountryRepository::class)]
class Country implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 2, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 60)]
    private string $name = '';

    #[ORM\Column(length: 5)]
    private string $locale = 'fr';

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public static function getTranslatableFields(): array
    {
        return ['name' => 'text'];
    }
}
