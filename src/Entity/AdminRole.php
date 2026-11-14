<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\AdminRoleRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: AdminRoleRepository::class)]
class AdminRole
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 60)]
    private string $name = '';

    #[ORM\Column(length: 7)]
    private string $color = '#B7AEFF';

    #[ORM\Column(length: 160)]
    private string $description = '';

    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column]
    private bool $system = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function isSystem(): bool
    {
        return $this->system;
    }

    public function setSystem(bool $system): static
    {
        $this->system = $system;

        return $this;
    }

    public function allows(string $permission): bool
    {
        return $this->system || in_array($permission, $this->permissions, true);
    }
}
