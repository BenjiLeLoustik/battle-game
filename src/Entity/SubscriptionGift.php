<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\SubscriptionGiftRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: SubscriptionGiftRepository::class)]
class SubscriptionGift
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 64, unique: true)]
    private string $claimKey = '';

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(ShopProduct::class, onDelete: 'SET NULL')]
    private ?ShopProduct $offer = null;

    #[ORM\ManyToOne(ShopOrder::class, onDelete: 'SET NULL')]
    private ?ShopOrder $sourceOrder = null;

    #[ORM\Column(length: 80)]
    private string $offerName = '';

    #[ORM\Column(type: 'json')]
    private array $items = [];

    public function getClaimKey(): string
    {
        return $this->claimKey;
    }

    public function setClaimKey(string $value): static
    {
        $this->claimKey = $value;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $value): static
    {
        $this->user = $value;

        return $this;
    }

    public function getOffer(): ?ShopProduct
    {
        return $this->offer;
    }

    public function setOffer(?ShopProduct $value): static
    {
        $this->offer = $value;

        return $this;
    }

    public function getSourceOrder(): ?ShopOrder
    {
        return $this->sourceOrder;
    }

    public function setSourceOrder(?ShopOrder $value): static
    {
        $this->sourceOrder = $value;

        return $this;
    }

    public function getOfferName(): string
    {
        return $this->offerName;
    }

    public function setOfferName(string $value): static
    {
        $this->offerName = $value;

        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $value): static
    {
        $this->items = array_values($value);

        return $this;
    }

    public function getPending(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (array $item): bool => $item['type'] === 'physical'
                && $item['status'] === 'pending',
        ));
    }
}