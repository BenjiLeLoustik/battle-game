<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ChatChannelRepository;
use App\Shared\Content\TranslatableInterface;
use App\Shared\Social\Enum\ChatAccessEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ChatChannelRepository::class)]
class ChatChannel implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 40, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 60)]
    private string $name = '';

    #[ORM\Column(length: 160)]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: ChatAccessEnum::class)]
    private ChatAccessEnum $readAccess = ChatAccessEnum::EVERYONE;

    #[ORM\Column(length: 20, enumType: ChatAccessEnum::class)]
    private ChatAccessEnum $writeAccess = ChatAccessEnum::MEMBERS;

    #[ORM\Column]
    private int $cooldown = 1;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $active = true;

    public static function getTranslatableFields(): array
    {
        return ['name' => 'text', 'description' => 'text'];
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getReadAccess(): ChatAccessEnum
    {
        return $this->readAccess;
    }

    public function setReadAccess(ChatAccessEnum $readAccess): static
    {
        $this->readAccess = $readAccess;

        return $this;
    }

    public function getWriteAccess(): ChatAccessEnum
    {
        return $this->writeAccess;
    }

    public function setWriteAccess(ChatAccessEnum $writeAccess): static
    {
        $this->writeAccess = $writeAccess;

        return $this;
    }

    public function getCooldown(): int
    {
        return $this->cooldown;
    }

    public function setCooldown(int $cooldown): static
    {
        $this->cooldown = $cooldown;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->readAccess === ChatAccessEnum::EVERYONE;
    }
}
