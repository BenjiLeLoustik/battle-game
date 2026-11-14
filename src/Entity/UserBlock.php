<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\UserBlockRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: UserBlockRepository::class)]
class UserBlock
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $blocker;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $blocked;

    public function getBlocker(): User
    {
        return $this->blocker;
    }

    public function setBlocker(User $blocker): static
    {
        $this->blocker = $blocker;

        return $this;
    }

    public function getBlocked(): User
    {
        return $this->blocked;
    }

    public function setBlocked(User $blocked): static
    {
        $this->blocked = $blocked;

        return $this;
    }
}
