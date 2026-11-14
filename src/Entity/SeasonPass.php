<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\SeasonPassRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: SeasonPassRepository::class)]
class SeasonPass
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(Season::class, nullable: false, onDelete: 'CASCADE')]
    private Season $season;

    #[ORM\Column]
    private int $xp = 0;

    #[ORM\Column(type: 'json')]
    private array $claimed = [];


    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    public function setSeason(Season $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getXp(): int
    {
        return $this->xp;
    }

    public function setXp(int $xp): static
    {
        $this->xp = $xp;

        return $this;
    }

    public function getClaimed(): array
    {
        return $this->claimed;
    }

    public function setClaimed(array $claimed): static
    {
        $this->claimed = $claimed;

        return $this;
    }
}
