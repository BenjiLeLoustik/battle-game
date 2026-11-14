<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\PageRepository;
use App\Shared\Content\TranslatableInterface;
use App\Shared\Page\Enum\PageFooterEnum;
use App\Shared\Page\Enum\PageFormatEnum;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: PageRepository::class)]
class Page implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $path = '';

    #[ORM\Column(length: 160)]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: PageFormatEnum::class)]
    private PageFormatEnum $format = PageFormatEnum::MARKDOWN;

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(type: 'text')]
    private string $style = '';

    #[ORM\Column(length: 20, enumType: PageFooterEnum::class)]
    private PageFooterEnum $footer = PageFooterEnum::NONE;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $published = false;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getUrl(): string
    {
        return '/' . $this->path;
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

    public function getFormat(): PageFormatEnum
    {
        return $this->format;
    }

    public function setFormat(PageFormatEnum $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function setStyle(string $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function getFooter(): PageFooterEnum
    {
        return $this->footer;
    }

    public function setFooter(PageFooterEnum $footer): static
    {
        $this->footer = $footer;

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

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;

        return $this;
    }

    public static function getTranslatableFields(): array
    {
        return ['title' => 'text', 'description' => 'text'];
    }
}
