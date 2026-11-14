<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\RoomRepository;
use App\Shared\Room\Enum\RoomStatusEnum;
use App\Shared\Room\Enum\RoomVisibilityEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: RoomRepository::class)]
class Room
{
    use IdTrait;
    use TimestampTrait;

    public const SIZES = [2, 4, 6, 8, 10];
    public const CHOICES = [3, 4];
    public const DURATIONS = [20, 30, 45];

    #[ORM\Column(length: 60)]
    private string $name = '';

    #[ORM\Column(length: 7, unique: true)]
    private string $code = '';

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $host = null;

    #[ORM\Column(length: 20, enumType: RoomVisibilityEnum::class)]
    private RoomVisibilityEnum $visibility = RoomVisibilityEnum::CODE;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $password = null;

    #[ORM\Column]
    private int $maxPlayers = 8;

    #[ORM\Column]
    private int $choices = 4;

    #[ORM\Column]
    private int $voteDuration = 20;

    #[ORM\Column]
    private bool $guests = true;

    #[ORM\Column]
    private bool $spectators = true;

    #[ORM\Column]
    private bool $permanent = false;

    #[ORM\Column]
    private bool $official = false;

    #[ORM\Column(length: 20, enumType: RoomStatusEnum::class)]
    private RoomStatusEnum $status = RoomStatusEnum::WAITING;

    #[ORM\Column(type: 'json')]
    private array $round = [];

    #[ORM\Column(type: 'json')]
    private array $history = [];

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $matchCode = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getHost(): ?User
    {
        return $this->host;
    }

    public function setHost(?User $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function isHost(User $user): bool
    {
        return $this->host !== null && $this->host->getId() === $user->getId();
    }

    public function getVisibility(): RoomVisibilityEnum
    {
        return $this->visibility;
    }

    public function setVisibility(RoomVisibilityEnum $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function isProtected(): bool
    {
        return $this->visibility === RoomVisibilityEnum::PASSWORD;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getMaxPlayers(): int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;

        return $this;
    }

    public function getChoices(): int
    {
        return $this->choices;
    }

    public function setChoices(int $choices): static
    {
        $this->choices = $choices;

        return $this;
    }

    public function getVoteDuration(): int
    {
        return $this->voteDuration;
    }

    public function setVoteDuration(int $voteDuration): static
    {
        $this->voteDuration = $voteDuration;

        return $this;
    }

    public function allowsGuests(): bool
    {
        return $this->guests;
    }

    public function setGuests(bool $guests): static
    {
        $this->guests = $guests;

        return $this;
    }

    public function allowsSpectators(): bool
    {
        return $this->spectators;
    }

    public function setSpectators(bool $spectators): static
    {
        $this->spectators = $spectators;

        return $this;
    }

    public function isPermanent(): bool
    {
        return $this->permanent;
    }

    public function setPermanent(bool $permanent): static
    {
        $this->permanent = $permanent;

        return $this;
    }

    public function isOfficial(): bool
    {
        return $this->official;
    }

    public function setOfficial(bool $official): static
    {
        $this->official = $official;

        return $this;
    }

    public function getStatus(): RoomStatusEnum
    {
        return $this->status;
    }

    public function setStatus(RoomStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRound(): array
    {
        return $this->round;
    }

    public function setRound(array $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function resetRound(): static
    {
        $this->round = ['number' => $this->round['number'] ?? 0];

        return $this;
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function addHistory(int $game): static
    {
        $this->history[] = $game;

        return $this;
    }

    public function getMatchCode(): ?string
    {
        return $this->matchCode;
    }

    public function setMatchCode(?string $matchCode): static
    {
        $this->matchCode = $matchCode;

        return $this;
    }
}
