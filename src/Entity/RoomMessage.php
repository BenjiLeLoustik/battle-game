<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\RoomMessageRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: RoomMessageRepository::class)]
class RoomMessage
{
    use IdTrait;
    use TimestampTrait;

    public const MAX_LENGTH = 500;

    #[ORM\ManyToOne(Room::class, nullable: false, onDelete: 'CASCADE')]
    private Room $room;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 500)]
    private string $text = '';

    public function getRoom(): Room
    {
        return $this->room;
    }

    public function setRoom(Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->user === null;
    }
}
