<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\SettingRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: SettingRepository::class)]
class Setting
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80, unique: true)]
    private string $name = '';

    #[ORM\Column(type: 'json')]
    private array $value = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getValue(): array
    {
        return $this->value;
    }

    public function setValue(array $value): static
    {
        $this->value = $value;

        return $this;
    }
}
