<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\StudioBlockRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: StudioBlockRepository::class)]
class StudioBlock
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 60)]
    private string $name = '';

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: 'json')]
    private array $definition = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = mb_substr(trim($name), 0, 60);

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function setDefinition(array $definition): static
    {
        $this->definition = $definition;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->name,
            'author' => $this->author?->getUsername(),
            'authorId' => $this->author?->getId(),
            'definition' => $this->definition,
        ];
    }
}
