<?php

namespace App\Entity\Trait;

use NeoPHP\Package\Orm\Mapping as ORM;
use DateTime;

trait TimestampTrait
{
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $updatedAt = null;

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function initializeTimestamp(): void
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdated(): void
    {
        $this->updatedAt = new DateTime();
    }
}