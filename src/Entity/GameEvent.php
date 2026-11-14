<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameEventRepository;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameEventRepository::class)]
class GameEvent
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 300)]
    private string $description = '';

    #[ORM\ManyToOne(Game::class, onDelete: 'SET NULL')]
    private ?Game $game = null;

    #[ORM\Column(type: 'datetime')]
    private DateTime $startsAt;

    #[ORM\Column(type: 'datetime')]
    private DateTime $endsAt;

    #[ORM\Column]
    private float $xpMultiplier = 1;

    #[ORM\Column]
    private float $coinsMultiplier = 1;

    #[ORM\Column(length: 7)]
    private string $color = '#FFC94A';

    #[ORM\Column]
    private bool $active = true;


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

    public function getStartsAt(): DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(DateTime $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(DateTime $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getXpMultiplier(): float
    {
        return $this->xpMultiplier;
    }

    public function setXpMultiplier(float $xpMultiplier): static
    {
        $this->xpMultiplier = $xpMultiplier;

        return $this;
    }

    public function getCoinsMultiplier(): float
    {
        return $this->coinsMultiplier;
    }

    public function setCoinsMultiplier(float $coinsMultiplier): static
    {
        $this->coinsMultiplier = $coinsMultiplier;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isLive(?DateTime $now = null): bool
    {
        $now ??= new DateTime();

        return $this->active && $this->startsAt <= $now && $this->endsAt > $now;
    }

    public function appliesTo(?Game $game): bool
    {
        return $this->game === null || ($game !== null && $this->game->getId() === $game->getId());
    }
}
