<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\BannerListRepository;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: BannerListRepository::class)]
class BannerList
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 60)]
    private string $name = '';

    #[ORM\Column]
    private bool $live = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $scheduledAt = null;

    public function getScheduledAt(): ?DateTime
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?DateTime $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    public function setLive(bool $live): static
    {
        $this->live = $live;

        return $this;
    }
}
