<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ChatMessageRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ChatMessageRepository::class)]
class ChatMessage
{
    use IdTrait;
    use TimestampTrait;

    public const MAX_LENGTH = 1000;

    #[ORM\Column(length: 40)]
    private string $channel = '';

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 1000)]
    private string $text = '';

    #[ORM\ManyToOne(Room::class, onDelete: 'SET NULL')]
    private ?Room $room = null;

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

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

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }
}
