<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameMemberRepository;
use App\Shared\Game\Enum\GameMemberRoleEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameMemberRepository::class)]
class GameMember
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20, enumType: GameMemberRoleEnum::class)]
    private GameMemberRoleEnum $role = GameMemberRoleEnum::DEVELOPER;

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

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

    public function getRole(): GameMemberRoleEnum
    {
        return $this->role;
    }

    public function setRole(GameMemberRoleEnum $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isLead(): bool
    {
        return $this->role === GameMemberRoleEnum::LEAD;
    }
}