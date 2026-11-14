<?php

namespace App\Entity;

use App\Admin\Studio\Enum\GameTaskStatusEnum;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameTaskRepository;
use DateTime;
use NeoPHP\Package\Orm\Collection\ArrayCollection;
use NeoPHP\Package\Orm\Contract\CollectionInterface;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameTaskRepository::class)]
class GameTask
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'json')]
    private array $checklist = [];

    #[ORM\Column(length: 20, enumType: GameTaskStatusEnum::class)]
    private GameTaskStatusEnum $status = GameTaskStatusEnum::TODO;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $startAt = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $endAt = null;

    #[ORM\ManyToMany(User::class, joinTable: 'game_task_user')]
    private CollectionInterface $assignees;

    public function __construct()
    {
        $this->assignees = new ArrayCollection();
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getChecklist(): array
    {
        return $this->checklist;
    }

    public function setChecklist(array $checklist): static
    {
        $this->checklist = $checklist;

        return $this;
    }

    public function getChecklistText(): string
    {
        return implode("\n", array_column($this->checklist, 'label'));
    }

    public function setChecklistText(string $text): static
    {
        $done = array_column($this->checklist, 'done', 'label');
        $labels = array_unique(array_filter(array_map(fn (string $line): string => mb_substr(trim($line), 0, 160), explode("\n", $text))));

        return $this->setChecklist(array_values(array_map(fn (string $label): array => ['label' => $label, 'done' => $done[$label] ?? false], $labels)));
    }

    public function toggleItem(int $index): static
    {
        if (isset($this->checklist[$index])) {
            $this->checklist[$index]['done'] = !$this->checklist[$index]['done'];
        }

        return $this;
    }

    public function getProgress(): array
    {
        return [
            'done' => count(array_filter(array_column($this->checklist, 'done'))),
            'total' => count($this->checklist),
        ];
    }

    public function getStatus(): GameTaskStatusEnum
    {
        return $this->status;
    }

    public function setStatus(GameTaskStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartAt(): ?DateTime
    {
        return $this->startAt;
    }

    public function setStartAt(?DateTime $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?DateTime
    {
        return $this->endAt;
    }

    public function setEndAt(?DateTime $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function isLate(): bool
    {
        return $this->status !== GameTaskStatusEnum::DONE && $this->endAt !== null && $this->endAt->format('Y-m-d') < date('Y-m-d');
    }

    public function getAssignees(): CollectionInterface
    {
        return $this->assignees;
    }

    public function setAssignees(array $users): static
    {
        $this->assignees->clear();

        foreach ($users as $user) {
            $this->assignees->add($user);
        }

        return $this;
    }

    public function isAssigned(User $user): bool
    {
        return $this->assignees->exists(fn (User $assignee): bool => $assignee->getId() === $user->getId());
    }
}
