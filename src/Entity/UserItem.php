<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\UserItemRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: UserItemRepository::class)]
class UserItem
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(ShopProduct::class, nullable: false, onDelete: 'CASCADE')]
    private ShopProduct $product;

    #[ORM\Column]
    private bool $equipped = false;


    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getProduct(): ShopProduct
    {
        return $this->product;
    }

    public function setProduct(ShopProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function isEquipped(): bool
    {
        return $this->equipped;
    }

    public function setEquipped(bool $equipped): static
    {
        $this->equipped = $equipped;

        return $this;
    }
}
