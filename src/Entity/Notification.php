<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\NotificationRepository;
use App\Shared\Social\Enum\NotificationTypeEnum;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: NotificationRepository::class)]
class Notification
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 30, enumType: NotificationTypeEnum::class)]
    private NotificationTypeEnum $type = NotificationTypeEnum::MESSAGE;

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $readAt = null;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): NotificationTypeEnum
    {
        return $this->type;
    }

    public function setType(NotificationTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getReadAt(): ?DateTime
    {
        return $this->readAt;
    }

    public function setReadAt(?DateTime $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }
}
