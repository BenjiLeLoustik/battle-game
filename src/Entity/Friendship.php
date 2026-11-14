<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\FriendshipRepository;
use App\Shared\Social\Enum\FriendshipStatusEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: FriendshipRepository::class)]
class Friendship
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $requester;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $addressee;

    #[ORM\Column(length: 20, enumType: FriendshipStatusEnum::class)]
    private FriendshipStatusEnum $status = FriendshipStatusEnum::PENDING;

    public function getRequester(): User
    {
        return $this->requester;
    }

    public function setRequester(User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getAddressee(): User
    {
        return $this->addressee;
    }

    public function setAddressee(User $addressee): static
    {
        $this->addressee = $addressee;

        return $this;
    }

    public function getStatus(): FriendshipStatusEnum
    {
        return $this->status;
    }

    public function setStatus(FriendshipStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function other(User $user): User
    {
        return $this->requester->getId() === $user->getId() ? $this->addressee : $this->requester;
    }

    public function isAccepted(): bool
    {
        return $this->status === FriendshipStatusEnum::ACCEPTED;
    }
}
