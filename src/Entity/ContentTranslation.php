<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Repository\ContentTranslationRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ContentTranslationRepository::class)]
#[ORM\Index(['type', 'object_id', 'locale', 'field'], unique: true)]
class ContentTranslation
{
    use IdTrait;

    #[ORM\Column(length: 40)]
    private string $type = '';

    #[ORM\Column]
    private int $objectId = 0;

    #[ORM\Column(length: 5)]
    private string $locale = '';

    #[ORM\Column(length: 40)]
    private string $field = '';

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getObjectId(): int
    {
        return $this->objectId;
    }

    public function setObjectId(int $objectId): static
    {
        $this->objectId = $objectId;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }
}
