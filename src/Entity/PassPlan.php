<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\PassPlanRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: PassPlanRepository::class)]
class PassPlan
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(type: 'json')]
    private array $tiers = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getTiers(): array
    {
        return array_values($this->tiers);
    }

    public function setTiers(array $tiers): static
    {
        $this->tiers = array_values($tiers);

        return $this;
    }
}
