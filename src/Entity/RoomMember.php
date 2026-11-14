<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\RoomMemberRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: RoomMemberRepository::class)]
class RoomMember
{
    use IdTrait;
    use TimestampTrait;

    public const PRESENCE_WINDOW = 45000;

    #[ORM\ManyToOne(Room::class, nullable: false, onDelete: 'CASCADE')]
    private Room $room;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private bool $spectator = false;

    #[ORM\Column(type: 'bigint')]
    private int $seenAt = 0;

    public function getRoom(): Room
    {
        return $this->room;
    }

    public function setRoom(Room $room): static
    {
        $this->room = $room;

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

    public function isSpectator(): bool
    {
        return $this->spectator;
    }

    public function setSpectator(bool $spectator): static
    {
        $this->spectator = $spectator;

        return $this;
    }

    public function getSeenAt(): int
    {
        return (int) $this->seenAt;
    }

    public function setSeenAt(int $seenAt): static
    {
        $this->seenAt = $seenAt;

        return $this;
    }

    public function isPresent(int $now): bool
    {
        return $now - $this->getSeenAt() < self::PRESENCE_WINDOW;
    }
}
