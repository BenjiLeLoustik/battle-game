<?php

namespace App\Shared\Leaderboard;

use App\Entity\Game;
use App\Entity\Season;
use App\Entity\User;
use App\Repository\GameResultRepository;
use App\Repository\SeasonRepository;
use App\Shared\Leaderboard\Enum\LeaderboardPeriodEnum;
use App\Shared\Translation\Translator;
use App\Shared\User\LevelManager;
use DateTime;

class LeaderboardManager
{
    private const SIZE = 50;
    private const TREND_DAYS = 7;

    public function __construct(
        protected GameResultRepository $results,
        protected SeasonRepository $seasons,
        protected LevelManager $levels,
    ) {
    }

    public function build(?Game $game, ?LeaderboardPeriodEnum $period, ?User $user): array
    {
        $season = $this->seasons->current();
        $periods = array_values(array_filter(LeaderboardPeriodEnum::cases(), fn (LeaderboardPeriodEnum $case): bool => $season !== null || $case !== LeaderboardPeriodEnum::SEASON));
        $period = in_array($period, $periods, true) ? $period : ($season !== null ? LeaderboardPeriodEnum::SEASON : LeaderboardPeriodEnum::ALL);
        [$since, $until] = $this->range($period, $season);

        $ranking = $this->rank($this->results->ranking($game, $since, $until));
        $previous = array_flip(array_column($this->previous($game, $since), 'id'));
        $visible = array_slice($ranking, 0, self::SIZE);
        $me = $user !== null ? (current(array_filter($ranking, fn (array $row): bool => (int) $row['id'] === $user->getId())) ?: null) : null;

        if ($me !== null && $me['rank'] > self::SIZE) {
            $visible[] = $me;
        }

        $ids = array_column($visible, 'id');
        $experience = $this->results->experience($ids);
        $favorites = $this->results->favorites($ids, $since, $until);

        $visible = array_map(fn (array $row): array => $this->anonymize($row, $user) + [
                'initials' => User::initialsOf((string) $row['first_name'], (string) $row['last_name']),
                'level' => $this->levels->level((int) ($experience[$row['id']] ?? 0)),
                'favorite' => $favorites[$row['id']] ?? null,
                'trend' => isset($previous[$row['id']]) ? $previous[$row['id']] + 1 - $row['rank'] : null,
            ], $visible);

        return [
            'season' => $season,
            'period' => $period,
            'periods' => $periods,
            'podium' => array_slice($visible, 0, 3),
            'rows' => array_slice($visible, 3),
            'me' => $me['id'] ?? null,
        ];
    }

    protected function anonymize(array $row, ?User $user): array
    {
        if ($row['listed'] !== 'false' || (int) $row['id'] === $user?->getId()) {
            return $row + ['anonymous' => false];
        }

        $name = Translator::trans('site.leaderboard.anonymous', [], 'messages');

        return ['username' => $name, 'first_name' => $name, 'last_name' => '', 'avatar' => null, 'anonymous' => true] + $row;
    }

    protected function range(LeaderboardPeriodEnum $period, ?Season $season): array
    {
        return match ($period) {
            LeaderboardPeriodEnum::WEEK => [new DateTime('monday this week'), null],
            LeaderboardPeriodEnum::MONTH => [new DateTime('first day of this month 00:00'), null],
            LeaderboardPeriodEnum::SEASON => [$season->getStartsAt(), $season->getEndsAt()],
            LeaderboardPeriodEnum::ALL => [null, null],
        };
    }

    protected function previous(?Game $game, ?DateTime $since): array
    {
        $until = new DateTime('-' . self::TREND_DAYS . ' days');

        return $since !== null && $until <= $since ? [] : $this->results->ranking($game, $since, $until);
    }

    protected function rank(array $rows): array
    {
        return array_map(fn (array $row, int $index): array => $row + ['rank' => $index + 1], $rows, array_keys($rows));
    }
}