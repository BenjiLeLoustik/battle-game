<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\TournamentEntryRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: TournamentEntryRepository::class)]
class TournamentEntry
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Tournament::class, nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;


    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function setTournament(Tournament $tournament): static
    {
        $this->tournament = $tournament;

        return $this;
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
}
