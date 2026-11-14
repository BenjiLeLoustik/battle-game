<?php

namespace App\Site\Search;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Shared\Content\ContentTranslationManager;
use NeoPHP\Component\Routing\RoutingManagerInterface;

class SearchManager
{
    public const MIN_LENGTH = 2;
    public const SUGGESTIONS = 5;
    public const RESULTS = 30;

    public function __construct(
        protected GameRepository $games,
        protected UserRepository $users,
        protected ContentTranslationManager $contents,
        protected RoutingManagerInterface $routing,
    ) {
    }

    public static function normalize(string $query): string
    {
        return mb_substr(trim($query), 0, 60);
    }

    public function search(string $query, int $limit): array
    {
        if (mb_strlen($query) < self::MIN_LENGTH) {
            return ['games' => [], 'players' => []];
        }

        $needle = mb_strtolower($query);
        $games = array_filter($this->games->findLive(), fn (Game $game): bool => str_contains(mb_strtolower($this->contents->localize($game, 'name') . ' ' . ($game->getCategory() === null ? '' : $this->contents->localize($game->getCategory(), 'name'))), $needle));

        return [
            'games' => array_map($this->game(...), array_slice(array_values($games), 0, $limit)),
            'players' => array_map($this->player(...), $this->users->findPlayers($query, $limit)),
        ];
    }

    protected function game(Game $game): array
    {
        return [
            'name' => $this->contents->localize($game, 'name'),
            'meta' => $game->getCategory() === null ? '' : $this->contents->localize($game->getCategory(), 'name'),
            'color' => $game->getColor(),
            'url' => $this->routing->generate('site_game_show', ['slug' => $game->getSlug()]),
        ];
    }

    protected function player(User $user): array
    {
        return [
            'name' => $user->getUsername(),
            'meta' => $user->getHandle(),
            'avatar' => $user->getAvatar(),
            'initials' => $user->getInitials(),
            'url' => $this->routing->generate('site_profile_show', ['username' => $user->getUsername()]),
        ];
    }
}
