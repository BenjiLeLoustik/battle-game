<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameResultRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameResultRepository::class)]
class GameResult
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(GameMatch::class, nullable: false, onDelete: 'CASCADE')]
    private GameMatch $match;

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $position;

    #[ORM\Column]
    private bool $won;

    #[ORM\Column]
    private int $points = 0;

    #[ORM\Column(type: 'json')]
    private array $stats = [];

    public function getMatch(): GameMatch
    {
        return $this->match;
    }

    public function setMatch(GameMatch $match): static
    {
        $this->match = $match;
        $this->game = $match->getGame();

        return $this;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        $this->won = $position === 1;

        return $this;
    }

    public function isWon(): bool
    {
        return $this->won;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    public function setStats(array $stats): static
    {
        $this->stats = array_filter($stats, fn (mixed $value): bool => $value !== null);

        return $this;
    }
}