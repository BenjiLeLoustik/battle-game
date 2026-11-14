<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\VoucherRepository;
use App\Shared\Shop\Enum\VoucherKindEnum;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: VoucherRepository::class)]
class Voucher
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 30, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 10, enumType: VoucherKindEnum::class)]
    private VoucherKindEnum $kind = VoucherKindEnum::PROMO;

    #[ORM\Column(nullable: true)]
    private ?int $percent = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $coins = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxUses = null;

    #[ORM\Column]
    private int $uses = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endsAt = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\ManyToOne(ShopOrder::class, onDelete: 'SET NULL')]
    private ?ShopOrder $order = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $note = null;

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getKind(): VoucherKindEnum
    {
        return $this->kind;
    }

    public function setKind(VoucherKindEnum $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    public function getPercent(): ?int
    {
        return $this->percent;
    }

    public function setPercent(?int $percent): static
    {
        $this->percent = $percent;

        return $this;
    }

    public function getAmountCents(): ?int
    {
        return $this->amountCents;
    }

    public function setAmountCents(?int $amountCents): static
    {
        $this->amountCents = $amountCents;

        return $this;
    }

    public function getCoins(): ?int
    {
        return $this->coins;
    }

    public function setCoins(?int $coins): static
    {
        $this->coins = $coins;

        return $this;
    }

    public function getMaxUses(): ?int
    {
        return $this->maxUses;
    }

    public function setMaxUses(?int $maxUses): static
    {
        $this->maxUses = $maxUses;

        return $this;
    }

    public function getUses(): int
    {
        return $this->uses;
    }

    public function setUses(int $uses): static
    {
        $this->uses = $uses;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getOrder(): ?ShopOrder
    {
        return $this->order;
    }

    public function setOrder(?ShopOrder $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function isUsable(): bool
    {
        return $this->active && ($this->maxUses === null || $this->uses < $this->maxUses) && ($this->endsAt === null || $this->endsAt > new DateTime());
    }

    public function discount(int $cents): int
    {
        $off = $this->percent !== null ? (int) round($cents * $this->percent / 100) : (int) $this->amountCents;

        return max(0, min($off, $cents - 50));
    }
}
