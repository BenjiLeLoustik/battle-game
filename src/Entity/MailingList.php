<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\MailingListRepository;
use App\Shared\Mail\Enum\MailingAudienceEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: MailingListRepository::class)]
class MailingList
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 160)]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: MailingAudienceEnum::class)]
    private MailingAudienceEnum $audience = MailingAudienceEnum::ALL;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\ManyToOne(Game::class, onDelete: 'SET NULL')]
    private ?Game $game = null;

    #[ORM\Column]
    private bool $optIn = true;

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

    public function getAudience(): MailingAudienceEnum
    {
        return $this->audience;
    }

    public function setAudience(MailingAudienceEnum $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function isOptIn(): bool
    {
        return $this->optIn;
    }

    public function setOptIn(bool $optIn): static
    {
        $this->optIn = $optIn;

        return $this;
    }
}
