<?php

namespace App\Shared\Social;

use App\Entity\RoomMember;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\RoomMemberRepository;
use App\Repository\UserRepository;
use DateTime;

class PresenceManager
{
    public const ONLINE_WINDOW = 300;
    private const TOUCH_DELAY = 60;
    private const MATCH_WINDOW = 600;

    public function __construct(
        protected UserRepository $users,
        protected GameMatchRepository $matches,
        protected RoomMemberRepository $members,
    ) {
    }

    public function touch(User $user): void
    {
        if ($user->getSeenAt() === null || $user->getSeenAt() < new DateTime('-' . self::TOUCH_DELAY . ' seconds')) {
            $this->users->getOrm()->getConnection()->executeStatement(
                'UPDATE `user` SET seen_at = NOW() WHERE id = :id',
                ['id' => $user->getId()],
            );
            $user->setSeenAt(new DateTime());
        }
    }

    public function isOnline(User $user): bool
    {
        return $user->getPreferences()->get('privacy', 'online')
            && $user->getSeenAt() !== null
            && $user->getSeenAt() >= new DateTime('-' . self::ONLINE_WINDOW . ' seconds');
    }

    public function status(User $user): array
    {
        if (!$this->isOnline($user)) {
            return ['state' => 'offline', 'seenAt' => $user->getPreferences()->get('privacy', 'online') ? $user->getSeenAt() : null];
        }

        $match = $this->users->getOrm()->getConnection()->fetchAssociative(
            'SELECT m.code, g.name FROM game_match m JOIN game g ON g.id = m.game_id WHERE m.status = :status AND m.updated_at >= :since'
            . ' AND JSON_SEARCH(m.players, \'one\', :player, NULL, \'$[*].id\') IS NOT NULL ORDER BY m.id DESC LIMIT 1',
            ['status' => 'playing', 'since' => (new DateTime('-' . self::MATCH_WINDOW . ' seconds'))->format('Y-m-d H:i:s'), 'player' => (string) $user->getId()],
        );

        if ($match !== null && $match !== false) {
            return ['state' => 'playing', 'game' => $match['name'], 'match' => $match['code']];
        }

        $now = (int) floor(microtime(true) * 1000);

        foreach ($this->members->findBy(['user' => $user]) as $member) {
            if ($member->isPresent($now)) {
                return ['state' => 'room', 'room' => $member->getRoom()];
            }
        }

        return ['state' => 'online'];
    }

    public function online(int $limit): array
    {
        $ids = $this->users->getOrm()->getConnection()->fetchFirstColumn(
            'SELECT id FROM `user` WHERE guest = 0 AND seen_at >= :since AND COALESCE(JSON_EXTRACT(preferences, \'$.privacy.online\'), TRUE) <> FALSE ORDER BY seen_at DESC LIMIT ' . $limit,
            ['since' => (new DateTime('-' . self::ONLINE_WINDOW . ' seconds'))->format('Y-m-d H:i:s')],
        );

        return array_values(array_filter(array_map(fn (mixed $id): ?User => $this->users->find((int) $id), $ids)));
    }

    public function countOnline(): int
    {
        return (int) $this->users->getOrm()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM `user` WHERE guest = 0 AND seen_at >= :since AND COALESCE(JSON_EXTRACT(preferences, \'$.privacy.online\'), TRUE) <> FALSE',
            ['since' => (new DateTime('-' . self::ONLINE_WINDOW . ' seconds'))->format('Y-m-d H:i:s')],
        );
    }
}
