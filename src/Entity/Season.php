<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\SeasonRepository;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: SeasonRepository::class)]
class Season
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(type: 'datetime')]
    private DateTime $startsAt;

    #[ORM\Column(type: 'datetime')]
    private DateTime $endsAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $rewardedAt = null;

    #[ORM\ManyToOne(PassPlan::class, onDelete: 'SET NULL')]
    private ?PassPlan $pass = null;

    public function getPass(): ?PassPlan
    {
        return $this->pass;
    }

    public function setPass(?PassPlan $pass): static
    {
        $this->pass = $pass;

        return $this;
    }

    public function getState(): string
    {
        $now = new DateTime();

        return $this->startsAt > $now ? 'upcoming' : ($this->endsAt <= $now ? 'finished' : 'live');
    }

    public function getRewardedAt(): ?DateTime
    {
        return $this->rewardedAt;
    }

    public function setRewardedAt(?DateTime $rewardedAt): static
    {
        $this->rewardedAt = $rewardedAt;

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

    public function getStartsAt(): DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(DateTime $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(DateTime $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getDaysLeft(): int
    {
        return max(0, (int) ceil(($this->endsAt->getTimestamp() - time()) / 86400));
    }
}