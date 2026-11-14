<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\BannerRepository;
use App\Shared\Banner\Enum\BannerAudienceEnum;
use App\Shared\Banner\Enum\BannerPagesEnum;
use App\Shared\Banner\Enum\BannerTypeEnum;
use DateTime;
use App\Shared\Content\TranslatableInterface;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: BannerRepository::class)]
class Banner implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(BannerList::class, nullable: false, onDelete: 'CASCADE')]
    private BannerList $list;

    #[ORM\Column(length: 160)]
    private string $text = '';

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $cta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(length: 20, enumType: BannerTypeEnum::class)]
    private BannerTypeEnum $type = BannerTypeEnum::INFO;

    #[ORM\Column(length: 20, enumType: BannerAudienceEnum::class)]
    private BannerAudienceEnum $audience = BannerAudienceEnum::ALL;

    #[ORM\Column(length: 20, enumType: BannerPagesEnum::class)]
    private BannerPagesEnum $pages = BannerPagesEnum::ALL;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $startsAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $endsAt = null;

    #[ORM\Column]
    private bool $dismissible = true;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $position = 0;

    public function getList(): BannerList
    {
        return $this->list;
    }

    public function setList(BannerList $list): static
    {
        $this->list = $list;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getCta(): ?string
    {
        return $this->cta;
    }

    public function setCta(?string $cta): static
    {
        $this->cta = $cta;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): BannerTypeEnum
    {
        return $this->type;
    }

    public function setType(BannerTypeEnum $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAudience(): BannerAudienceEnum
    {
        return $this->audience;
    }

    public function setAudience(BannerAudienceEnum $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function getPages(): BannerPagesEnum
    {
        return $this->pages;
    }

    public function setPages(BannerPagesEnum $pages): static
    {
        $this->pages = $pages;

        return $this;
    }

    public function getStartsAt(): ?DateTime
    {
        return $this->startsAt;
    }

    public function setStartsAt(?DateTime $startsAt): static
    {
        $this->startsAt = $startsAt;

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

    public function isDismissible(): bool
    {
        return $this->dismissible;
    }

    public function setDismissible(bool $dismissible): static
    {
        $this->dismissible = $dismissible;

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

    public function isLive(DateTime $now = new DateTime()): bool
    {
        return $this->active && ($this->startsAt === null || $this->startsAt <= $now) && ($this->endsAt === null || $this->endsAt > $now);
    }

    public static function getTranslatableFields(): array
    {
        return ['text' => 'text', 'cta' => 'text'];
    }
}
