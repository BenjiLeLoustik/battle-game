<?php

namespace App\Admin\Studio;

use App\Entity\Game;
use App\Shared\Validator\Constraint\Unique;
use NeoPHP\Component\Validator\Constraint as Assert;

class NewGameRequest
{
    #[Assert\NotBlank(message: 'studio.name.not_blank')]
    #[Assert\Length(max: 60, maxMessage: 'studio.name.too_long')]
    public string $name = '';

    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'studio.slug.invalid')]
    #[Unique(message: 'studio.slug.taken', entity: Game::class, field: 'slug')]
    public string $slug = '';

    #[Assert\NotBlank(message: 'studio.category.not_blank')]
    public string $category = '';

    #[Assert\Range(min: 1, max: 32, notInRangeMessage: 'studio.players.range')]
    public int $minPlayers = 2;

    #[Assert\Range(min: 1, max: 32, notInRangeMessage: 'studio.players.range')]
    #[Assert\GreaterThanOrEqual(propertyPath: 'minPlayers', message: 'studio.players.order')]
    public int $maxPlayers = 4;

    #[Assert\Length(max: 160, maxMessage: 'studio.description.too_long')]
    public string $description = '';

    public array $team = [];

    public static function fromArray(array $data): static
    {
        $request = new static();
        $request->name = trim((string) ($data['name'] ?? ''));
        $request->slug = self::slugify($request->name);
        $request->category = trim((string) ($data['category'] ?? ''));
        $request->minPlayers = (int) ($data['min_players'] ?? 2);
        $request->maxPlayers = (int) ($data['max_players'] ?? 4);
        $request->description = trim((string) ($data['description'] ?? ''));
        $request->team = array_map('intval', (array) ($data['team'] ?? []));

        return $request;
    }

    public static function slugify(string $value): string
    {
        $value = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');
    }

    public function getDirectory(): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $this->slug)));
    }
}