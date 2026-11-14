<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ChatReadRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ChatReadRepository::class)]
class ChatRead
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 40)]
    private string $channel = '';

    #[ORM\Column]
    private int $lastId = 0;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getLastId(): int
    {
        return $this->lastId;
    }

    public function setLastId(int $lastId): static
    {
        $this->lastId = $lastId;

        return $this;
    }
}
