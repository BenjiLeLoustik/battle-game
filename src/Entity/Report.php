<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ReportRepository;
use App\Shared\Social\Enum\ReportReasonEnum;
use App\Shared\Social\Enum\ReportStatusEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ReportRepository::class)]
class Report
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $reporter = null;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $reported;

    #[ORM\Column(length: 20, enumType: ReportReasonEnum::class)]
    private ReportReasonEnum $reason = ReportReasonEnum::OTHER;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 20, enumType: ReportStatusEnum::class)]
    private ReportStatusEnum $status = ReportStatusEnum::OPEN;

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;

        return $this;
    }

    public function getReported(): User
    {
        return $this->reported;
    }

    public function setReported(User $reported): static
    {
        $this->reported = $reported;

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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getStatus(): ReportStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ReportStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }
}
