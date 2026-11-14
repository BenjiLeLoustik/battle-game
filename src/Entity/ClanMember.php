<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ClanMemberRepository;
use App\Shared\Clan\Enum\ClanRoleEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ClanMemberRepository::class)]
class ClanMember
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Clan::class, nullable: false, onDelete: 'CASCADE')]
    private Clan $clan;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10, enumType: ClanRoleEnum::class)]
    private ClanRoleEnum $role = ClanRoleEnum::MEMBER;


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

    public function getRole(): ClanRoleEnum
    {
        return $this->role;
    }

    public function setRole(ClanRoleEnum $role): static
    {
        $this->role = $role;

        return $this;
    }
}
