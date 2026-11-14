<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ClanRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ClanRepository::class)]
class Clan
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 40, unique: true)]
    private string $name = '';

    #[ORM\Column(length: 5, unique: true)]
    private string $tag = '';

    #[ORM\Column(length: 300)]
    private string $description = '';

    #[ORM\Column(length: 7)]
    private string $color = '#2EE6D6';

    #[ORM\Column]
    private bool $open = true;


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function setTag(string $tag): static
    {
        $this->tag = $tag;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function setOpen(bool $open): static
    {
        $this->open = $open;

        return $this;
    }
}
