<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\UserBadgeRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: UserBadgeRepository::class)]
class UserBadge
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(Badge::class, nullable: false, onDelete: 'CASCADE')]
    private Badge $badge;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getBadge(): Badge
    {
        return $this->badge;
    }

    public function setBadge(Badge $badge): static
    {
        $this->badge = $badge;

        return $this;
    }
}
