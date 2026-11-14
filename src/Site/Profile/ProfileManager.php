<?php

namespace App\Site\Profile;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\GameResultRepository;
use App\Shared\Badge\BadgeManager;
use App\Shared\User\LevelManager;

class ProfileManager
{
    private const RECENT = 8;
    private const FEATURED = 3;

    public function __construct(
        protected GameResultRepository $results,
        protected GameRepository $games,
        protected LevelManager $levels,
        protected BadgeManager $badges,
    ) {
    }

    public function isVisible(User $user, ?User $viewer): bool
    {
        return !$user->isGuest() && $user->getDeletionRequestedAt() === null
            && ($user->getPreferences()->get('privacy', 'public') || $viewer?->getId() === $user->getId());
    }

    public function build(User $user, ?User $viewer): array
    {
        $totals = $this->results->totals($user);
        $points = (int) $totals['points'];
        $level = $this->levels->level($points);
        $me = $viewer?->getId() === $user->getId();

        return [
            'user' => $user,
            'me' => $me,
            'played' => (int) $totals['played'],
            'won' => (int) $totals['won'],
            'rank' => $points > 0 ? $this->results->globalRank($user, $points) : null,
            'progress' => $this->levels->progress($points),
            'titles' => $this->levels->titles($level),
            'nextTitle' => $this->levels->nextTitle($level),
            'games' => $this->games($user, $me),
            'badges' => $this->badges->collection($user, null, $me),
            'featured' => $this->badges->featured($user, self::FEATURED),
            'recent' => array_map($this->recentRow(...), $this->results->recent($user, self::RECENT)),
            'versus' => $viewer !== null && $viewer->getId() !== $user->getId() ? $this->versus($viewer, $user) : null,
        ];
    }

    protected function games(User $user, bool $me): array
    {
        $rows = [];

        foreach ($this->results->perGame($user) as $row) {
            $game = $this->games->find((int) $row['game_id']);

            if ($game === null) {
                continue;
            }

            $board = $game->getLeaderboardStat();
            $summary = $board !== null ? $this->results->summary($game, $user, [$board]) : [];
            $best = $board !== null ? ($summary[$board['key']] ?? null) : null;

            $rows[] = [
                'game' => $game,
                'played' => (int) $row['played'],
                'won' => (int) $row['won'],
                'rate' => (int) round($row['won'] / max(1, $row['played']) * 100),
                'progress' => $this->levels->progress((int) $row['points']),
                'board' => $board,
                'best' => $best,
                'rank' => $best !== null ? $this->results->rank($game, $board, (string) $best) : null,
                'badges' => $this->badges->collection($user, $game, $me),
            ];
        }

        return $rows;
    }

    protected function recentRow(GameResult $result): array
    {
        $board = $result->getGame()->getLeaderboardStat();

        return [
            'game' => $result->getGame(),
            'position' => $result->getPosition(),
            'won' => $result->isWon(),
            'points' => $result->getPoints(),
            'board' => $board,
            'score' => $board !== null ? ($result->getStats()[$board['key']] ?? null) : null,
            'date' => $result->getCreatedAt(),
        ];
    }

    protected function versus(User $viewer, User $user): array
    {
        $rows = $this->results->headToHead($viewer, $user);
        $byGame = [];

        foreach ($rows as $row) {
            $byGame[$row['game_id']] ??= ['mine' => 0, 'theirs' => 0];

            if ($row['mine'] !== $row['theirs']) {
                $byGame[$row['game_id']][$row['mine'] < $row['theirs'] ? 'mine' : 'theirs']++;
            }
        }

        uasort($byGame, fn (array $a, array $b): int => ($b['mine'] - $b['theirs']) <=> ($a['mine'] - $a['theirs']));
        $best = array_key_first($byGame);

        return [
            'played' => count($rows),
            'mine' => array_sum(array_column($byGame, 'mine')),
            'theirs' => array_sum(array_column($byGame, 'theirs')),
            'last' => $rows[0]['created_at'] ?? null,
            'best' => $best !== null && $byGame[$best]['mine'] > $byGame[$best]['theirs'] ? ['game' => $this->games->find((int) $best), 'score' => $byGame[$best]] : null,
        ];
    }
}
