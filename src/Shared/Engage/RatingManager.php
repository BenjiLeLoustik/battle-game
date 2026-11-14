<?php

namespace App\Shared\Engage;

use App\Entity\Game;
use App\Entity\User;
use App\Entity\UserRating;
use App\Repository\UserRatingRepository;
use App\Shared\Setting\SettingManager;

class RatingManager
{
    public const START = 1000;
    public const DEFAULT_TIERS = [
        ['name' => 'Bronze', 'min' => 0, 'color' => '#E39B6B'],
        ['name' => 'Argent', 'min' => 1100, 'color' => '#C9CDE3'],
        ['name' => 'Or', 'min' => 1250, 'color' => '#FFC94A'],
        ['name' => 'Platine', 'min' => 1400, 'color' => '#2EE6D6'],
        ['name' => 'Diamant', 'min' => 1550, 'color' => '#A78BFA'],
        ['name' => 'Maître', 'min' => 1700, 'color' => '#FF3DCB'],
    ];
    private const K = 32;

    protected ?array $tiers = null;

    public function __construct(protected UserRatingRepository $ratings, protected SettingManager $settings)
    {
    }

    public function tiers(): array
    {
        if ($this->tiers !== null) {
            return $this->tiers;
        }

        $tiers = [];

        foreach ((array) $this->settings->get('ranked_tiers') as $tier) {
            $name = is_array($tier) ? mb_substr(trim((string) ($tier['name'] ?? '')), 0, 30) : '';

            if ($name !== '') {
                $tiers[] = ['name' => $name, 'min' => max(0, (int) ($tier['min'] ?? 0)), 'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($tier['color'] ?? '')) ? strtoupper($tier['color']) : '#8A93B5'];
            }
        }

        $tiers = $tiers ?: self::DEFAULT_TIERS;
        usort($tiers, fn (array $a, array $b): int => $a['min'] <=> $b['min']);
        $tiers[0]['min'] = 0;

        return $this->tiers = $tiers;
    }

    public function step(): int
    {
        return max(10, (int) $this->settings->get('ranked_step'));
    }

    public function rating(User $user, Game $game): UserRating
    {
        return $this->ratings->findOneBy(['user' => $user, 'game' => $game]) ?? (new UserRating())->setUser($user)->setGame($game);
    }

    public function update(Game $game, array $positions, array $users): array
    {
        $current = [];

        foreach ($positions as $id => $position) {
            if (isset($users[$id]) && !$users[$id]->isGuest()) {
                $current[$id] = $this->rating($users[$id], $game);
            }
        }

        $count = count($current);

        if ($count < 2) {
            return [];
        }

        $deltas = array_fill_keys(array_keys($current), 0.0);

        foreach ($current as $a => $ratingA) {
            foreach ($current as $b => $ratingB) {
                if ($a === $b) {
                    continue;
                }

                $expected = 1 / (1 + 10 ** (($ratingB->getRating() - $ratingA->getRating()) / 400));
                $score = $positions[$a] < $positions[$b] ? 1.0 : ($positions[$a] === $positions[$b] ? 0.5 : 0.0);
                $deltas[$a] += self::K * ($score - $expected) / ($count - 1);
            }
        }

        $changes = [];

        foreach ($current as $id => $rating) {
            $before = $rating->getRating();
            $after = max(100, (int) round($before + $deltas[$id]));
            $rating->setRating($after)->setPeak(max($rating->getPeak(), $after))->setGames($rating->getGames() + 1)->setWins($rating->getWins() + ($positions[$id] === 1 ? 1 : 0));
            $this->ratings->save($rating);
            $changes[$id] = ['before' => $before, 'after' => $after, 'tier' => $this->tier($after)];
        }

        $this->ratings->getOrm()->flush();

        return $changes;
    }

    public function tier(int $rating): array
    {
        $tiers = $this->tiers();
        $index = 0;

        foreach ($tiers as $position => $tier) {
            if ($rating >= $tier['min']) {
                $index = $position;
            }
        }

        $tier = $tiers[$index];
        $last = $index === count($tiers) - 1;
        $division = 1;
        $min = $tier['min'];

        if ($last) {
            $division = intdiv($rating - $tier['min'], $this->step()) + 1;
            $min = $tier['min'] + ($division - 1) * $this->step();
            $next = ['label' => $this->label($tier['name'], $division + 1), 'min' => $min + $this->step(), 'color' => $tier['color']];
        } else {
            $next = ['label' => $tiers[$index + 1]['name'], 'min' => $tiers[$index + 1]['min'], 'color' => $tiers[$index + 1]['color']];
        }

        return [
            'name' => $tier['name'],
            'label' => $this->label($tier['name'], $division),
            'color' => $tier['color'],
            'min' => $min,
            'next' => $next,
            'progress' => (int) round(max(0, min(1, ($rating - $min) / max(1, $next['min'] - $min))) * 100),
        ];
    }

    protected function label(string $name, int $division): string
    {
        if ($division <= 1) {
            return $name;
        }

        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $roman = '';

        foreach ($map as $letter => $value) {
            while ($division >= $value) {
                $roman .= $letter;
                $division -= $value;
            }
        }

        return $name . ' ' . $roman;
    }

    public function leaderboard(Game $game, int $limit = 50): array
    {
        return array_map(fn (UserRating $rating): array => ['user' => $rating->getUser(), 'rating' => $rating->getRating(), 'peak' => $rating->getPeak(), 'games' => $rating->getGames(), 'wins' => $rating->getWins(), 'tier' => $this->tier($rating->getRating())], $this->ratings->findBy(['game' => $game], ['rating' => 'DESC', 'games' => 'DESC'], $limit));
    }

    public function best(User $user): array
    {
        return array_map(fn (UserRating $rating): array => ['game' => $rating->getGame(), 'rating' => $rating->getRating(), 'tier' => $this->tier($rating->getRating()), 'games' => $rating->getGames()], $this->ratings->findBy(['user' => $user], ['rating' => 'DESC'], 5));
    }
}
