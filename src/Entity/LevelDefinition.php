<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\LevelDefinitionRepository;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: LevelDefinitionRepository::class)]
class LevelDefinition implements TranslatableInterface
{
    use IdTrait;

    #[ORM\Column(unique: true)]
    private int $number = 1;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column]
    private int $requiredExperience = 0;

    #[ORM\ManyToOne(Badge::class, nullable: true, onDelete: 'SET NULL')]
    private ?Badge $badge = null;

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

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

    public function getRequiredExperience(): int
    {
        return $this->requiredExperience;
    }

    public function setRequiredExperience(int $requiredExperience): static
    {
        $this->requiredExperience = $requiredExperience;

        return $this;
    }

    public function getBadge(): ?Badge
    {
        return $this->badge;
    }

    public function setBadge(?Badge $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public static function getTranslatableFields(): array
    {
        return ['name' => 'text'];
    }
}