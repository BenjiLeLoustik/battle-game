<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\MaintenanceRepository;
use App\Shared\Maintenance\Enum\MaintenanceMatchPolicyEnum;
use App\Shared\Maintenance\Enum\MaintenanceStatusEnum;
use App\Shared\Maintenance\Enum\MaintenanceTargetEnum;
use DateTime;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: MaintenanceRepository::class)]
class Maintenance implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    public const BLOCK_BEFORE_MINUTES = 15;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 20, enumType: MaintenanceTargetEnum::class)]
    private MaintenanceTargetEnum $target = MaintenanceTargetEnum::SITE;

    #[ORM\Column(type: 'json')]
    private array $games = [];

    #[ORM\Column(type: 'json')]
    private array $features = [];

    #[ORM\Column(type: 'datetime')]
    private DateTime $startsAt;

    #[ORM\Column(type: 'datetime')]
    private DateTime $endsAt;

    #[ORM\Column(length: 20, enumType: MaintenanceMatchPolicyEnum::class)]
    private MaintenanceMatchPolicyEnum $matchPolicy = MaintenanceMatchPolicyEnum::FINISH;

    #[ORM\Column]
    private bool $blockBefore = true;

    #[ORM\Column(type: 'json')]
    private array $pages = [];

    #[ORM\Column(type: 'json')]
    private array $paths = [];

    #[ORM\Column]
    private int $notice = 1440;

    #[ORM\Column(type: 'text')]
    private string $message = '';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $cancelledAt = null;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $author = null;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTarget(): MaintenanceTargetEnum
    {
        return $this->target;
    }

    public function setTarget(MaintenanceTargetEnum $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getGames(): array
    {
        return $this->games;
    }

    public function setGames(array $games): static
    {
        $this->games = array_values(array_map('intval', $games));

        return $this;
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function setFeatures(array $features): static
    {
        $this->features = array_values($features);

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

    public function getMatchPolicy(): MaintenanceMatchPolicyEnum
    {
        return $this->matchPolicy;
    }

    public function setMatchPolicy(MaintenanceMatchPolicyEnum $matchPolicy): static
    {
        $this->matchPolicy = $matchPolicy;

        return $this;
    }

    public function isBlockBefore(): bool
    {
        return $this->blockBefore;
    }

    public function setBlockBefore(bool $blockBefore): static
    {
        $this->blockBefore = $blockBefore;

        return $this;
    }

    public function getPages(): array
    {
        return $this->pages;
    }

    public function setPages(array $pages): static
    {
        $this->pages = array_values($pages);

        return $this;
    }

    public function getPaths(): array
    {
        return $this->paths;
    }

    public function setPaths(array $paths): static
    {
        $this->paths = array_values($paths);

        return $this;
    }

    public function getNotice(): int
    {
        return $this->notice;
    }

    public function setNotice(int $notice): static
    {
        $this->notice = $notice;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

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

    public function end(): static
    {
        $this->endedAt = new DateTime();

        return $this;
    }

    public function cancel(): static
    {
        $this->cancelledAt = new DateTime();

        return $this;
    }

    public function getFinishAt(): DateTime
    {
        return $this->endedAt ?? $this->endsAt;
    }

    public function getStatus(DateTime $now = new DateTime()): MaintenanceStatusEnum
    {
        return match (true) {
            $this->cancelledAt !== null => MaintenanceStatusEnum::CANCELLED,
            $now >= $this->getFinishAt() => MaintenanceStatusEnum::DONE,
            $now >= $this->startsAt => MaintenanceStatusEnum::RUNNING,
            default => MaintenanceStatusEnum::PLANNED,
        };
    }

    public function isRunning(): bool
    {
        return $this->getStatus() === MaintenanceStatusEnum::RUNNING;
    }

    public function isAnnounced(DateTime $now = new DateTime()): bool
    {
        return $this->isRunning()
            || ($this->getStatus($now) === MaintenanceStatusEnum::PLANNED
                && $this->notice > 0
                && $now >= (clone $this->startsAt)->modify('-' . $this->notice . ' minutes'));
    }

    public function concerns(Game $game): bool
    {
        return $this->target === MaintenanceTargetEnum::SITE
            || ($this->target === MaintenanceTargetEnum::GAMES && in_array($game->getId(), $this->games, true));
    }

    public function blocksNewMatches(Game $game, DateTime $now = new DateTime()): bool
    {
        $status = $this->getStatus($now);
        $soon = $this->blockBefore
            && $status === MaintenanceStatusEnum::PLANNED
            && $now >= (clone $this->startsAt)->modify('-' . self::BLOCK_BEFORE_MINUTES . ' minutes');

        return $this->concerns($game) && ($status === MaintenanceStatusEnum::RUNNING || $soon);
    }

    public static function getTranslatableFields(): array
    {
        return ['title' => 'text', 'message' => 'area'];
    }
}
