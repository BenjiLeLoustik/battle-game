<?php

namespace App\Entity;

use App\Admin\Studio\Enum\GameReviewStatusEnum;
use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\GameReviewRepository;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: GameReviewRepository::class)]
class GameReview
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Game::class, nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $requester = null;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $reviewer = null;

    #[ORM\Column(length: 20, enumType: GameReviewStatusEnum::class)]
    private GameReviewStatusEnum $status = GameReviewStatusEnum::PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $reviewedAt = null;

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function getStatus(): GameReviewStatusEnum
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === GameReviewStatusEnum::PENDING;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message === null || trim($message) === '' ? null : trim($message);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function getReviewedAt(): ?DateTime
    {
        return $this->reviewedAt;
    }

    public function close(GameReviewStatusEnum $status, User $reviewer, ?string $comment = null, ?string $version = null): static
    {
        $this->status = $status;
        $this->reviewer = $reviewer;
        $this->comment = $comment === null || trim($comment) === '' ? null : trim($comment);
        $this->version = $version;
        $this->reviewedAt = new DateTime();

        return $this;
    }
}
