<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ShopOrderRepository;
use App\Shared\Shop\Enum\ShopOrderStatusEnum;
use App\Shared\Shop\Enum\ShopPaymentEnum;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ShopOrderRepository::class)]
class ShopOrder
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 16, unique: true)]
    private string $reference = '';

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(ShopProduct::class, onDelete: 'SET NULL')]
    private ?ShopProduct $product = null;

    #[ORM\Column(length: 80)]
    private string $productName = '';

    #[ORM\Column(length: 20, enumType: ShopProductTypeEnum::class)]
    private ShopProductTypeEnum $type = ShopProductTypeEnum::COSMETIC;

    #[ORM\Column(length: 10, enumType: ShopPaymentEnum::class)]
    private ShopPaymentEnum $payment = ShopPaymentEnum::CARD;

    #[ORM\Column(nullable: true)]
    private ?int $amountCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $amountCoins = null;

    #[ORM\Column(length: 20, enumType: ShopOrderStatusEnum::class)]
    private ShopOrderStatusEnum $status = ShopOrderStatusEnum::PENDING;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeSession = null;

    #[ORM\Column(type: 'json')]
    private array $shipping = [];

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $tracking = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $paidAt = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $variant = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $voucherCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $discountCents = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $includedGifts = null;

    public function getVoucherCode(): ?string
    {
        return $this->voucherCode;
    }

    public function setVoucherCode(?string $voucherCode): static
    {
        $this->voucherCode = $voucherCode;

        return $this;
    }

    public function getDiscountCents(): ?int
    {
        return $this->discountCents;
    }

    public function setDiscountCents(?int $discountCents): static
    {
        $this->discountCents = $discountCents;

        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $termsAcceptedAt = null;

    public function getVariant(): ?string
    {
        return $this->variant;
    }

    public function setVariant(?string $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getTermsAcceptedAt(): ?DateTime
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?DateTime $termsAcceptedAt): static
    {
        $this->termsAcceptedAt = $termsAcceptedAt;

        return $this;
    }


    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

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

    public function getProduct(): ?ShopProduct
    {
        return $this->product;
    }

    public function setProduct(?ShopProduct $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getType(): ShopProductTypeEnum
    {
        return $this->type;
    }

    public function setType(ShopProductTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPayment(): ShopPaymentEnum
    {
        return $this->payment;
    }

    public function setPayment(ShopPaymentEnum $payment): static
    {
        $this->payment = $payment;

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

    public function getAmountCoins(): ?int
    {
        return $this->amountCoins;
    }

    public function setAmountCoins(?int $amountCoins): static
    {
        $this->amountCoins = $amountCoins;

        return $this;
    }

    public function getStatus(): ShopOrderStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ShopOrderStatusEnum $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStripeSession(): ?string
    {
        return $this->stripeSession;
    }

    public function setStripeSession(?string $stripeSession): static
    {
        $this->stripeSession = $stripeSession;

        return $this;
    }

    public function getShipping(): array
    {
        return $this->shipping;
    }

    public function setShipping(array $shipping): static
    {
        $this->shipping = $shipping;

        return $this;
    }

    public function getTracking(): ?string
    {
        return $this->tracking;
    }

    public function setTracking(?string $tracking): static
    {
        $this->tracking = $tracking;

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

    public function getPaidAt(): ?DateTime
    {
        return $this->paidAt;
    }

    public function setPaidAt(?DateTime $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    public function getIncludedGifts(): ?array
    {
        return $this->includedGifts;
    }

    public function setIncludedGifts(?array $value): static
    {
        $this->includedGifts = $value;

        return $this;
    }
}
