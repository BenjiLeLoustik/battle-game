<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ClanRequestRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ClanRequestRepository::class)]
class ClanRequest
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Clan::class, nullable: false, onDelete: 'CASCADE')]
    private Clan $clan;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;


    public function getClan(): Clan
    {
        return $this->clan;
    }

    public function setClan(Clan $clan): static
    {
        $this->clan = $clan;

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
