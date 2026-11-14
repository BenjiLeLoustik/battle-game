<?php

namespace App\Shared\Engage;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\User;
use App\Repository\GameResultRepository;
use App\Repository\SeasonRepository;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentRepository;
use DateTime;

class TournamentManager
{
    public const QUALIFIERS = ['open', 'general', 'game'];
    public const PERIODS = ['season', 'month', 'week', 'all'];

    public function __construct(
        protected TournamentRepository $tournaments,
        protected TournamentEntryRepository $entries,
        protected GameResultRepository $results,
        protected SeasonRepository $seasons,
    ) {
    }

    public function qualified(Tournament $tournament): array
    {
        if (!$tournament->isRestricted()) {
            return [];
        }

        $season = $this->seasons->current();
        [$since, $until] = match ($tournament->getQualifierPeriod()) {
            'week' => [new DateTime('monday this week'), null],
            'month' => [new DateTime('first day of this month 00:00'), null],
            'season' => $season !== null ? [$season->getStartsAt(), $season->getEndsAt()] : [null, null],
            default => [null, null],
        };

        $rows = $this->results->ranking($tournament->getQualifier() === 'game' ? $tournament->getGame() : null, $since, $until);

        return array_map(fn (array $row, int $index): array => $row + ['rank' => $index + 1, 'initials' => User::initialsOf((string) $row['first_name'], (string) $row['last_name'])], array_slice($rows, 0, (int) $tournament->getQualifierCount()), array_keys(array_slice($rows, 0, (int) $tournament->getQualifierCount())));
    }

    public function isQualified(Tournament $tournament, ?User $user): bool
    {
        return $user !== null && (!$tournament->isRestricted() || in_array($user->getId(), array_map('intval', array_column($this->qualified($tournament), 'id')), true));
    }

    public function registerQualified(Tournament $tournament): array
    {
        $added = [];

        foreach ($this->qualified($tournament) as $row) {
            $user = $this->tournaments->getOrm()->getRepository(User::class)->find((int) $row['id']);

            if ($user === null || $this->entry($tournament, $user) !== null || ($tournament->getMaxPlayers() !== null && $this->count($tournament) >= $tournament->getMaxPlayers())) {
                continue;
            }

            $this->entries->save((new TournamentEntry())->setTournament($tournament)->setUser($user), true);
            $added[] = $user;
        }

        return $added;
    }

    public function visible(): array
    {
        $list = $this->tournaments->findBy(['published' => true], ['startsAt' => 'DESC']);
        $order = ['live' => 0, 'upcoming' => 1, 'finished' => 2];
        usort($list, fn (Tournament $a, Tournament $b): int => $order[$a->getState()] <=> $order[$b->getState()] ?: ($a->getState() === 'finished' ? $b->getEndsAt() <=> $a->getEndsAt() : $a->getStartsAt() <=> $b->getStartsAt()));

        return $list;
    }

    public function entry(Tournament $tournament, ?User $user): ?TournamentEntry
    {
        return $user === null ? null : $this->entries->findOneBy(['tournament' => $tournament, 'user' => $user]);
    }

    public function count(Tournament $tournament): int
    {
        return $this->entries->count(['tournament' => $tournament]);
    }

    public function register(Tournament $tournament, User $user): void
    {
        if ($user->isGuest() || $tournament->getState() === 'finished' || $this->entry($tournament, $user) !== null || ($tournament->getMaxPlayers() !== null && $this->count($tournament) >= $tournament->getMaxPlayers())) {
            throw new TournamentException();
        }

        if (!$this->isQualified($tournament, $user)) {
            throw new TournamentQualificationException();
        }

        $this->entries->save((new TournamentEntry())->setTournament($tournament)->setUser($user), true);
    }

    public function unregister(Tournament $tournament, User $user): void
    {
        $entry = $this->entry($tournament, $user);

        if ($entry !== null && $tournament->getState() === 'upcoming') {
            $this->entries->delete($entry, true);
        }
    }

    public function standings(Tournament $tournament, int $limit = 100): array
    {
        $rows = $this->tournaments->getOrm()->getConnection()->fetchAllAssociative(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, COALESCE(SUM(r.points), 0) AS points, COUNT(r.id) AS games, COALESCE(SUM(r.won), 0) AS wins'
            . ' FROM tournament_entry e JOIN `user` u ON u.id = e.user_id'
            . ' LEFT JOIN game_result r ON r.user_id = e.user_id AND r.game_id = :game AND r.created_at >= GREATEST(:starts, e.created_at) AND r.created_at < :ends'
            . ' WHERE e.tournament_id = :tournament GROUP BY u.id, u.username, u.first_name, u.last_name, u.avatar ORDER BY points DESC, wins DESC, games ASC, u.id ASC LIMIT ' . $limit,
            [
                'game' => $tournament->getGame()->getId(),
                'starts' => $tournament->getStartsAt()->format('Y-m-d H:i:s'),
                'ends' => $tournament->getEndsAt()->format('Y-m-d H:i:s'),
                'tournament' => $tournament->getId(),
            ],
        );

        return array_map(fn (array $row): array => $row + ['initials' => User::initialsOf((string) $row['first_name'], (string) $row['last_name'])], $rows);
    }

    public function active(User $user): array
    {
        $now = new DateTime();

        return array_values(array_filter(array_map(fn (TournamentEntry $entry): Tournament => $entry->getTournament(), $this->entries->findBy(['user' => $user])), fn (Tournament $tournament): bool => $tournament->getEndsAt() > $now));
    }
}
