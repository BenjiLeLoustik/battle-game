<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\SanctionRepository;
use App\Shared\Moderation\Enum\SanctionTypeEnum;
use App\Shared\Social\Enum\ReportReasonEnum;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: SanctionRepository::class)]
class Sanction
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(length: 20, enumType: SanctionTypeEnum::class)]
    private SanctionTypeEnum $type = SanctionTypeEnum::WARNING;

    #[ORM\Column(length: 20, enumType: ReportReasonEnum::class)]
    private ReportReasonEnum $reason = ReportReasonEnum::OTHER;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endsAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $revokedAt = null;

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

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

    public function getType(): SanctionTypeEnum
    {
        return $this->type;
    }

    public function setType(SanctionTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getReason(): ReportReasonEnum
    {
        return $this->reason;
    }

    public function setReason(ReportReasonEnum $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getEndsAt(): ?DateTime
    {
        return $this->endsAt;
    }

    public function setEndsAt(?DateTime $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getRevokedAt(): ?DateTime
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?DateTime $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function isActive(DateTime $now = new DateTime()): bool
    {
        return $this->revokedAt === null && ($this->endsAt === null || $this->endsAt > $now);
    }
}
