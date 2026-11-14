<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\UserRatingRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: UserRatingRepository::class)]
class UserRating
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column]
    private int $rating = 1000;

    #[ORM\Column]
    private int $peak = 1000;

    #[ORM\Column]
    private int $games = 0;

    #[ORM\Column]
    private int $wins = 0;


    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getPeak(): int
    {
        return $this->peak;
    }

    public function setPeak(int $peak): static
    {
        $this->peak = $peak;

        return $this;
    }

    public function getGames(): int
    {
        return $this->games;
    }

    public function setGames(int $games): static
    {
        $this->games = $games;

        return $this;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function setWins(int $wins): static
    {
        $this->wins = $wins;

        return $this;
    }
}
