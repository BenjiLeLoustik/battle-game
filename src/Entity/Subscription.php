<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\SubscriptionRepository;
use App\Shared\Shop\Enum\SubscriptionStatusEnum;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: SubscriptionRepository::class)]
class Subscription
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(ShopProduct::class, onDelete: 'SET NULL')]
    private ?ShopProduct $product = null;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 20, enumType: SubscriptionStatusEnum::class)]
    private SubscriptionStatusEnum $status = SubscriptionStatusEnum::ACTIVE;

    #[ORM\Column(type: 'datetime')]
    private DateTime $endsAt;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeSubscription = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $stripeCustomer = null;


    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColor(): string
    {
        return (string) ($this->product?->option('color') ?: ShopProduct::PLAN_COLOR);
    }

    public function getBadge(): array
    {
        return $this->product?->getBadge() ?? ['color' => ShopProduct::PLAN_COLOR, 'icon' => 'crown', 'label' => $this->name, 'svg' => '', 'path' => ShopProduct::PLAN_PATHS['crown']];
    }

    public function getStatus(): SubscriptionStatusEnum
    {
        return $this->status;
    }

    public function setStatus(SubscriptionStatusEnum $status): static
    {
        $this->status = $status;

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

    public function getStripeSubscription(): ?string
    {
        return $this->stripeSubscription;
    }

    public function setStripeSubscription(?string $stripeSubscription): static
    {
        $this->stripeSubscription = $stripeSubscription;

        return $this;
    }

    public function getStripeCustomer(): ?string
    {
        return $this->stripeCustomer;
    }

    public function setStripeCustomer(?string $stripeCustomer): static
    {
        $this->stripeCustomer = $stripeCustomer;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->status !== SubscriptionStatusEnum::EXPIRED && $this->endsAt > new DateTime();
    }
}
