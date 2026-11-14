<?php

namespace App\Shared\Game;

use App\Entity\Game;
use App\Entity\GameMatch;
use App\Entity\GameResult;
use App\Entity\User;
use App\Repository\GameResultRepository;
use App\Repository\UserRepository;
use App\Shared\Badge\BadgeManager;
use App\Shared\Engage\EventManager;
use App\Shared\Engage\RatingManager;
use App\Shared\Engage\ReferralManager;
use App\Shared\Engage\SeasonPassManager;
use App\Shared\Game\Enum\GameMatchModeEnum;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\LifeManager;
use App\Shared\Shop\WalletManager;
use App\Shared\User\LevelManager;

class GameStatsManager
{
    private const LEADERBOARD_SIZE = 5;

    public function __construct(
        protected WalletManager $wallets,
        protected LifeManager $lives,
        protected SettingManager $settings,
        protected EventManager $events,
        protected SeasonPassManager $passes,
        protected ReferralManager $referrals,
        protected RatingManager $ratings,
        protected GameResultRepository $results,
        protected UserRepository $users,
        protected LevelManager $levels,
        protected BadgeManager $badges,
    ) {
    }

    public function record(GameMatch $match, array $results): void
    {
        $game = $match->getGame();
        $players = [];
        $multipliers = $this->events->multipliers($game);

        foreach ($results as $player => $result) {
            $user = $this->users->find((int) $player);

            if ($user === null) {
                continue;
            }

            $points = (int) round($game->pointsFor($result['position']) * $multipliers['xp']);
            $players[$player] = ['user' => $user, 'points' => $points, 'general' => $this->results->pointsOf($user, null), 'game' => $this->results->pointsOf($user, $game)];
            $this->results->save((new GameResult())
                ->setMatch($match)
                ->setUser($user)
                ->setPosition($result['position'])
                ->setPoints($points)
                ->setStats($result['stats'] ?? []));
        }

        $this->results->getOrm()->flush();
        $rewards = [];
        $ratings = $match->getMode() === GameMatchModeEnum::RANKED
            ? $this->ratings->update($game, array_map(fn (array $result): int => (int) $result['position'], array_intersect_key($results, $players)), array_map(fn (array $data) => $data['user'], $players))
            : [];

        foreach ($players as $player => $data) {
            $position = (int) $results[$player]['position'];
            $coins = $data['user']->isGuest() ? 0 : (int) round(((int) $this->settings->get('coins_per_match') + ($position === 1 ? (int) $this->settings->get('coins_per_win') : 0)) * $multipliers['coins']);
            $this->wallets->credit($data['user'], $coins, 'match', $game->getName());

            if ($position > 1) {
                $this->lives->lose($data['user']);
            }

            if (!$data['user']->isGuest()) {
                $this->passes->add($data['user'], $data['points']);
                $this->referrals->onMatch($data['user']);
            }

            $rewards[$player] = [
                'coins' => $coins,
                'lives' => $this->lives->status($data['user'])['left'],
                'xp' => $data['points'],
                'general' => $this->levels->change($data['general'], $data['points']),
                'game' => $this->levels->change($data['game'], $data['points']),
                'badges' => $this->badges->evaluate($data['user'], [$game]),
                'rating' => $ratings[$player] ?? null,
                'boost' => $multipliers['xp'] > 1 || $multipliers['coins'] > 1 ? $multipliers : null,
            ];
        }

        $match->setRewards($rewards);
    }

    public function page(Game $game, ?User $user): array
    {
        $board = $game->getLeaderboardStat();
        $leaderboard = $board !== null ? $this->results->leaderboard($game, $board, self::LEADERBOARD_SIZE) : [];
        $summary = $user !== null ? $this->results->summary($game, $user, $game->getStats()) : null;
        $best = $board !== null && $summary !== null ? $summary[$board['key']] : null;
        $me = null;

        if ($best !== null && !$user->isGuest() && !in_array($user->getId(), array_column($leaderboard, 'id'))) {
            $me = ['rank' => $this->results->rank($game, $board, $best), 'username' => $user->getUsername(), 'best' => $best];
        }

        return [
            'board' => $board,
            'record' => $leaderboard[0]['best'] ?? null,
            'best' => $best,
            'leaderboard' => $leaderboard,
            'me' => $me,
            'summary' => $summary,
            'details' => array_values(array_filter($game->getStats(), fn (array $stat): bool => $stat !== $board)),
        ];
    }
}