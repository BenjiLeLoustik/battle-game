<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameMatchRepository;
use App\Shared\Game\Enum\GameMatchModeEnum;
use App\Shared\Game\Enum\GameMatchStatusEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameMatchRepository::class)]
class GameMatch
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(length: 8, unique: true)]
    private string $code;

    #[ORM\Column(length: 20, enumType: GameMatchStatusEnum::class)]
    private GameMatchStatusEnum $status = GameMatchStatusEnum::WAITING;

    #[ORM\Column(length: 20, enumType: GameMatchModeEnum::class)]
    private GameMatchModeEnum $mode = GameMatchModeEnum::QUICK;

    #[ORM\Column(type: 'json')]
    private array $players = [];

    #[ORM\Column(type: 'json')]
    private array $settings = [];

    #[ORM\Column(type: 'json')]
    private array $state = [];

    #[ORM\Column(type: 'json')]
    private array $timeline = [];

    #[ORM\Column(type: 'json')]
    private array $votes = [];

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $nextCode = null;

    #[ORM\ManyToOne(Room::class, onDelete: 'SET NULL')]
    private ?Room $room = null;

    #[ORM\Column(type: 'json')]
    private array $rewards = [];

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

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

    public function getStatus(): GameMatchStatusEnum
    {
        return $this->status;
    }

    public function setStatus(GameMatchStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getMode(): GameMatchModeEnum
    {
        return $this->mode;
    }

    public function setMode(GameMatchModeEnum $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function isSolo(): bool
    {
        return $this->mode === GameMatchModeEnum::SOLO;
    }

    public function getPlayers(): array
    {
        return $this->players;
    }

    public function setPlayers(array $players): static
    {
        $this->players = array_values($players);

        return $this;
    }

    public function getPlayerIds(): array
    {
        return array_column($this->players, 'id');
    }

    public function hasPlayer(User $user): bool
    {
        return in_array((string) $user->getId(), $this->getPlayerIds(), true);
    }

    public function addPlayer(User $user): static
    {
        $now = (int) floor(microtime(true) * 1000);

        $this->players[] = [
            'id' => (string) $user->getId(),
            'username' => $user->getUsername(),
            'initials' => $user->getInitials(),
            'avatar' => $user->getAvatar(),
            'queuedAt' => $now,
            'queueSeenAt' => $now,
        ];

        return $this;
    }

    public function removePlayer(User $user): static
    {
        return $this->setPlayers(array_filter($this->players, fn (array $player): bool => $player['id'] !== (string) $user->getId()));
    }

    public function isFull(): bool
    {
        return count($this->players) >= $this->game->getMaxPlayers();
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function setSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function getState(): array
    {
        return $this->state;
    }

    public function setState(array $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getTimeline(): array
    {
        return $this->timeline;
    }

    public function mark(string $event, int $time): static
    {
        $this->timeline[$event] = $time;

        return $this;
    }

    public function unmark(string $event): static
    {
        unset($this->timeline[$event]);

        return $this;
    }

    public function getVotes(): array
    {
        return $this->votes;
    }

    public function vote(User $user, bool $replay): static
    {
        $this->votes[(string) $user->getId()] = $replay;

        return $this;
    }

    public function getReplayPlayers(): array
    {
        return array_values(array_filter($this->players, fn (array $player): bool => ($this->votes[$player['id']] ?? false) === true));
    }

    public function getNextCode(): ?string
    {
        return $this->nextCode;
    }

    public function setNextCode(?string $nextCode): static
    {
        $this->nextCode = $nextCode;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    public function getRewards(): array
    {
        return $this->rewards;
    }

    public function setRewards(array $rewards): static
    {
        $this->rewards = $rewards;

        return $this;
    }
}
