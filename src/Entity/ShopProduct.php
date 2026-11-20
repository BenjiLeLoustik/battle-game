<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\ShopProductRepository;
use App\Shared\Content\TranslatableInterface;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: ShopProductRepository::class)]
class ShopProduct implements TranslatableInterface
{
    public const PLAN_COLOR = '#7C5CFF';
    public const PLAN_ICONS = ['crown', 'star', 'diamond', 'bolt', 'heart', 'flame', 'shield', 'check'];
    public const PLAN_PATHS = ['crown' => 'M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8z', 'star' => 'M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9L12 2.5z', 'diamond' => 'M6 3h12l4 6-10 12L2 9l4-6z', 'bolt' => 'M13 2L4 14h7l-1 8 9-12h-7l1-8z', 'heart' => 'M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z', 'flame' => 'M12 2s6 5 6 11a6 6 0 0 1-12 0c0-3 2-5 2-5s0 3 2 3c0-4 2-9 2-9z', 'shield' => 'M12 2l8 3v6c0 5.5-3.5 9.5-8 11-4.5-1.5-8-5.5-8-11V5l8-3z', 'check' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.5 14.5l-4.5-4.5 1.6-1.6 2.9 2.9 6-6 1.6 1.6-7.6 7.6z'];

    public const SIZES = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL'];

    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 80)]
    private string $name = '';

    #[ORM\Column(length: 100, unique: true)]
    private string $slug = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: ShopProductTypeEnum::class)]
    private ShopProductTypeEnum $type = ShopProductTypeEnum::COSMETIC;

    #[ORM\Column(nullable: true)]
    private ?int $priceCents = null;

    #[ORM\Column(nullable: true)]
    private ?int $priceCoins = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column]
    private bool $active = false;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(nullable: true)]
    private ?int $stock = null;

    #[ORM\Column(type: 'json')]
    private array $payload = [];


    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getType(): ShopProductTypeEnum
    {
        return $this->type;
    }

    public function setType(ShopProductTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPriceCents(): ?int
    {
        return $this->priceCents;
    }

    public function setPriceCents(?int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getPriceCoins(): ?int
    {
        return $this->priceCoins;
    }

    public function setPriceCoins(?int $priceCoins): static
    {
        $this->priceCoins = $priceCoins;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->payload[$key] ?? $default;
    }

    public function getPerks(): string
    {
        return implode("\n", (array) $this->option('perks', []));
    }

    public function getBadge(): array
    {
        $legacy = in_array($this->option('icon'), self::PLAN_ICONS, true) ? $this->option('icon') : 'crown';
        $svg = (string) $this->option('icon_svg', '');

        return [
            'color' => (string) ($this->option('color') ?: self::PLAN_COLOR),
            'icon' => (string) ($this->option('icon') ?: 'crown'),
            'label' => (string) ($this->option('label') ?: $this->name),
            'svg' => $svg,
            'path' => $svg === '' ? self::PLAN_PATHS[$legacy] : null,
        ];
    }

    public function hasEarlyAccess(): bool
    {
        return (bool) $this->option('early_access', false);
    }

    public function getSizes(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->option('sizes', ''))), fn (string $size): bool => $size !== ''));
    }

    public function isUnlimited(): bool
    {
        return (int) $this->option('lives', 0) <= 0;
    }

    public static function getTranslatableFields(): array
    {
        return ['name' => 'text', 'description' => 'area', 'perks' => 'area'];
    }

    public function isAvailable(): bool
    {
        return $this->active && ($this->stock === null || $this->stock > 0) && ($this->priceCents !== null || $this->priceCoins !== null);
    }
}
