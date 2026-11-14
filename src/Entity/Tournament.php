<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\TournamentRepository;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: TournamentRepository::class)]
class Tournament
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column(type: 'datetime')]
    private DateTime $startsAt;

    #[ORM\Column(type: 'datetime')]
    private DateTime $endsAt;

    #[ORM\Column(nullable: true)]
    private ?int $maxPlayers = null;

    #[ORM\Column(length: 200)]
    private string $prizes = '';

    #[ORM\Column]
    private bool $published = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $rewardedAt = null;

    #[ORM\Column(type: 'json')]
    private array $rewards = [];

    #[ORM\Column(length: 10)]
    private string $qualifier = 'open';

    #[ORM\Column(nullable: true)]
    private ?int $qualifierCount = null;

    #[ORM\Column(length: 10)]
    private string $qualifierPeriod = 'season';

    #[ORM\Column(type: 'json')]
    private array $results = [];


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

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

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

    public function getMaxPlayers(): ?int
    {
        return $this->maxPlayers;
    }

    public function setMaxPlayers(?int $maxPlayers): static
    {
        $this->maxPlayers = $maxPlayers;

        return $this;
    }

    public function getPrizes(): string
    {
        return $this->prizes;
    }

    public function setPrizes(string $prizes): static
    {
        $this->prizes = $prizes;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
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

    public function getRewards(): array
    {
        return array_values($this->rewards);
    }

    public function setRewards(array $rewards): static
    {
        $this->rewards = array_values($rewards);

        return $this;
    }

    public function getRewardsByPlace(): array
    {
        $places = [];

        foreach ($this->getRewards() as $reward) {
            $places[(int) ($reward['place'] ?? 0)][] = $reward;
        }

        unset($places[0]);
        ksort($places);

        return $places;
    }

    public function getPool(): int
    {
        return array_sum(array_map(fn (array $reward): int => in_array($reward['type'] ?? '', ['coins', 'giftcard'], true) ? (int) ($reward['value'] ?? 0) : 0, $this->getRewards()));
    }

    public function getQualifier(): string
    {
        return $this->qualifier;
    }

    public function setQualifier(string $qualifier): static
    {
        $this->qualifier = $qualifier;

        return $this;
    }

    public function getQualifierCount(): ?int
    {
        return $this->qualifierCount;
    }

    public function setQualifierCount(?int $qualifierCount): static
    {
        $this->qualifierCount = $qualifierCount;

        return $this;
    }

    public function getQualifierPeriod(): string
    {
        return $this->qualifierPeriod;
    }

    public function setQualifierPeriod(string $qualifierPeriod): static
    {
        $this->qualifierPeriod = $qualifierPeriod;

        return $this;
    }

    public function isRestricted(): bool
    {
        return $this->qualifier !== 'open' && $this->qualifierCount !== null && $this->qualifierCount > 0;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function setResults(array $results): static
    {
        $this->results = $results;

        return $this;
    }

    public function getPrizeList(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $this->prizes)), fn (int $value): bool => $value > 0));
    }

    public function getState(): string
    {
        $now = new DateTime();

        return $now < $this->startsAt ? 'upcoming' : ($now < $this->endsAt ? 'live' : 'finished');
    }
}
