<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\InvoiceRepository;
use App\Shared\Shop\Enum\InvoiceKindEnum;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: InvoiceRepository::class)]
class Invoice
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 20, unique: true)]
    private string $number = '';

    #[ORM\Column(length: 10, enumType: InvoiceKindEnum::class)]
    private InvoiceKindEnum $kind = InvoiceKindEnum::INVOICE;

    #[ORM\ManyToOne(ShopOrder::class, onDelete: 'SET NULL')]
    private ?ShopOrder $order = null;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: 'json')]
    private array $seller = [];

    #[ORM\Column(type: 'json')]
    private array $buyer = [];

    #[ORM\Column(type: 'json')]
    private array $lines = [];

    #[ORM\Column]
    private int $totalCents = 0;

    #[ORM\Column]
    private int $vatCents = 0;

    #[ORM\Column]
    private float $vatRate = 0;

    #[ORM\Column(type: 'datetime')]
    private DateTime $issuedAt;

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getKind(): InvoiceKindEnum
    {
        return $this->kind;
    }

    public function setKind(InvoiceKindEnum $kind): static
    {
        $this->kind = $kind;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getSeller(): array
    {
        return $this->seller;
    }

    public function setSeller(array $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getBuyer(): array
    {
        return $this->buyer;
    }

    public function setBuyer(array $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function getLines(): array
    {
        return $this->lines;
    }

    public function setLines(array $lines): static
    {
        $this->lines = $lines;

        return $this;
    }

    public function getTotalCents(): int
    {
        return $this->totalCents;
    }

    public function setTotalCents(int $totalCents): static
    {
        $this->totalCents = $totalCents;

        return $this;
    }

    public function getVatCents(): int
    {
        return $this->vatCents;
    }

    public function setVatCents(int $vatCents): static
    {
        $this->vatCents = $vatCents;

        return $this;
    }

    public function getVatRate(): float
    {
        return $this->vatRate;
    }

    public function setVatRate(float $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function getIssuedAt(): DateTime
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(DateTime $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getNetCents(): int
    {
        return $this->totalCents - $this->vatCents;
    }
}
