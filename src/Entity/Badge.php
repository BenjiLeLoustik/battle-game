<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\BadgeRepository;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: BadgeRepository::class)]
class Badge implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 120, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 160)]
    private string $description = '';

    #[ORM\ManyToOne(Game::class, onDelete: 'CASCADE')]
    private ?Game $game = null;

    #[ORM\Column(length: 160)]
    private string $rule = '';

    #[ORM\Column(type: 'json')]
    private array $params = [];

    #[ORM\Column(type: 'json')]
    private array $design = [];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private bool $secret = false;

    #[ORM\Column]
    private int $position = 0;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getRule(): string
    {
        return $this->rule;
    }

    public function setRule(string $rule): static
    {
        $this->rule = $rule;

        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): static
    {
        $this->params = $params;

        return $this;
    }

    public function getDesign(): array
    {
        return $this->design;
    }

    public function setDesign(array $design): static
    {
        $this->design = $design;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function setSecret(bool $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public static function getTranslatableFields(): array
    {
        return ['name' => 'text', 'description' => 'text'];
    }
}
