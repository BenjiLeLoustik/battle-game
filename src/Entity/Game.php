<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\SluggerTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameRepository;
use App\Shared\Game\Enum\GameStatusEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameRepository::class)]
class Game
{
    use IdTrait;
    use SluggerTrait;
    use TimestampTrait;

    public const ASSET_URL = '/games';

    #[ORM\Column(length: 80)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $version;

    #[ORM\Column(length: 100)]
    private string $directory;

    #[ORM\ManyToOne(Category::class, onDelete: 'RESTRICT')]
    private ?Category $category = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $repository = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'json')]
    private array $manifest = [];

    #[ORM\Column(length: 20, enumType: GameStatusEnum::class)]
    private GameStatusEnum $status = GameStatusEnum::DEV;

    #[ORM\Column(type: 'json')]
    private array $history = [];

    #[ORM\Column(nullable: true)]
    private ?int $featuredPosition = null;

    #[ORM\Column]
    private bool $earlyAccess = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function setDirectory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getRepository(): ?string
    {
        return $this->repository;
    }

    public function setRepository(?string $repository, ?string $reference = null): static
    {
        $this->repository = $repository;
        $this->reference = $reference;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getManifest(): array
    {
        return $this->manifest;
    }

    public function setManifest(array $manifest): static
    {
        $this->manifest = $manifest;
        $this->name = $manifest['name'];
        $this->version = $manifest['version'];
        $this->setSlug($manifest['slug']);

        return $this;
    }

    public function getStatus(): GameStatusEnum
    {
        return $this->status;
    }

    public function setStatus(GameStatusEnum $status, ?User $user = null): static
    {
        $this->status = $status;
        $this->history[] = [
            'status' =>$status->value,
            'at' => date('Y-m-d H:i:s'),
            'user' => $user?->getUsername(),
        ];

        return $this;
    }

    public function getHistory(): array
    {
        return array_reverse($this->history);
    }

    public function isEarlyAccess(): bool
    {
        return $this->earlyAccess;
    }

    public function setEarlyAccess(bool $earlyAccess): static
    {
        $this->earlyAccess = $earlyAccess;

        return $this;
    }

    public function getFeaturedPosition(): ?int
    {
        return $this->featuredPosition;
    }

    public function setFeaturedPosition(?int $featuredPosition): static
    {
        $this->featuredPosition = $featuredPosition;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featuredPosition !== null;
    }

    public function isPlayableBy(bool $tester): bool
    {
        return in_array($this->status, [GameStatusEnum::LIVE, GameStatusEnum::MAINTENANCE], true) || ($tester && in_array($this->status, [GameStatusEnum::DEV, GameStatusEnum::TEST], true));
    }

    public function getDescription(): string
    {
        return (string) ($this->manifest['description'] ?? '');
    }

    public function getMinPlayers(): int
    {
        return (int) $this->manifest['players']['min'];
    }

    public function getMaxPlayers(): int
    {
        return (int) $this->manifest['players']['max'];
    }

    public function getPlayers(): string
    {
        return $this->getMinPlayers() === $this->getMaxPlayers()
            ? (string) $this->getMaxPlayers()
            : $this->getMinPlayers() . '-' . $this->getMaxPlayers();
    }

    public function getDuration(): int
    {
        return (int) ($this->manifest['duration'] ?? 0);
    }

    public function getColor(): string
    {
        return (string) ($this->manifest['colors']['primary'] ?? '#2EE6D6');
    }

    public function getColorAlt(): string
    {
        return (string) ($this->manifest['colors']['secondary'] ?? '#7C5CFF');
    }

    public function getIcon(): ?string
    {
        return isset($this->manifest['icon']) ? $this->getAssetUrl($this->manifest['icon']) : null;
    }

    public function getClient(): string
    {
        return $this->getAssetUrl($this->manifest['client']);
    }

    public function getStyle(): ?string
    {
        return isset($this->manifest['style']) ? $this->getAssetUrl($this->manifest['style']) : null;
    }

    public function getAssetUrl(string $path): string
    {
        return sprintf(
            '%s/%s/%s',
            self::ASSET_URL,
            $this->getSlug(),
            ltrim($path, '/')
        );
    }

    public function getAuthor(): string
    {
        return (string) ($this->manifest['author'] ?? '');
    }

    public function getRules(): array
    {
        return $this->manifest['rules'] ?? [];
    }

    public function getCover(): ?string
    {
        return isset($this->manifest['cover']) ? $this->getAssetUrl($this->manifest['cover']) : null;
    }

    public function getSdk(): string
    {
        return (string) ($this->manifest['sdk'] ?? '');
    }

    public function isSolo(): bool
    {
        return ($this->manifest['solo'] ?? false) === true;
    }

    public function pointsFor(int $position): int
    {
        return (int) ($this->manifest['points'][$position - 1] ?? 0);
    }

    public function getStats(): array
    {
        return $this->manifest['stats'] ?? [];
    }

    public function getLeaderboardStat(): ?array
    {
        foreach ($this->getStats() as $stat) {
            if (isset($stat['leaderboard'])) {
                return $stat;
            }
        }

        return null;
    }
}