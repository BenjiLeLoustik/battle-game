<?php

namespace App\Shared\Pagination;

class Pagination
{
    public const SIZE = 5;

    public function __construct(
        protected array $items,
        protected int $page,
        protected int $total,
        protected int $size = self::SIZE,
        protected string $name = 'page'
    ) {
    }

    public static function slice(iterable $items, int $page, int $size = self::SIZE, string $name = 'page'): static
    {
        $items = is_array($items) ? array_values($items) : iterator_to_array($items, false);
        $pages = max(1, (int) ceil(count($items) / $size));
        $page = min(max(1, $page), $pages);

        return new static(array_slice($items, ($page - 1) * $size, $size), $page, count($items), $size, $name);
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPages(): int
    {
        return max(1, (int) ceil($this->total / $this->size));
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->size;
    }

    public function getFrom(): int
    {
        return $this->total === 0 ? 0 : $this->getOffset() + 1;
    }

    public function getTo(): int
    {
        return min($this->getOffset() + $this->size, $this->total);
    }
}
