<?php

namespace App\Shared\Social;

use App\Entity\Friendship;
use App\Entity\Room;
use App\Entity\User;
use App\Entity\UserBlock;
use App\Repository\FriendshipRepository;
use App\Repository\RoomMemberRepository;
use App\Repository\UserBlockRepository;
use App\Repository\UserRepository;
use App\Shared\Badge\BadgeManager;
use App\Shared\Social\Enum\FriendshipStatusEnum;
use App\Shared\Social\Enum\NotificationTypeEnum;

class FriendManager
{
    private const RECENT = 6;

    public function __construct(
        protected FriendshipRepository $friendships,
        protected UserBlockRepository $blocks,
        protected UserRepository $users,
        protected RoomMemberRepository $members,
        protected NotificationManager $notifications,
        protected BadgeManager $badges,
    ) {
    }

    public function relation(User $user, User $other): ?Friendship
    {
        return $this->friendships->findOneBy(['requester' => $user, 'addressee' => $other])
            ?? $this->friendships->findOneBy(['requester' => $other, 'addressee' => $user]);
    }

    public function state(User $user, User $other): string
    {
        $relation = $this->relation($user, $other);

        return match (true) {
            $relation === null => 'none',
            $relation->isAccepted() => 'friends',
            $relation->getRequester()->getId() === $user->getId() => 'sent',
            default => 'received',
        };
    }

    public function areFriends(User $user, User $other): bool
    {
        return $this->relation($user, $other)?->isAccepted() ?? false;
    }

    public function friends(User $user): array
    {
        $friends = array_map(
            fn (Friendship $friendship): User => $friendship->other($user),
            array_filter($this->all($user), fn (Friendship $friendship): bool => $friendship->isAccepted()),
        );
        usort($friends, fn (User $a, User $b): int => strcasecmp($a->getUsername(), $b->getUsername()));

        return $friends;
    }

    public function requests(User $user): array
    {
        return array_map(
            fn (Friendship $friendship): User => $friendship->getRequester(),
            $this->friendships->findBy(['addressee' => $user, 'status' => FriendshipStatusEnum::PENDING], ['id' => 'DESC']),
        );
    }

    public function sent(User $user): array
    {
        return array_map(
            fn (Friendship $friendship): User => $friendship->getAddressee(),
            $this->friendships->findBy(['requester' => $user, 'status' => FriendshipStatusEnum::PENDING], ['id' => 'DESC']),
        );
    }

    public function request(User $user, ?User $other): ?string
    {
        if ($other === null || $other->isGuest() || $other->getDeletionRequestedAt() !== null) {
            return 'social.user.not_found';
        }

        if ($other->getId() === $user->getId()) {
            return 'social.friend.self';
        }

        if ($this->isBlocked($user, $other)) {
            return 'social.friend.blocked';
        }

        $relation = $this->relation($user, $other);

        if ($relation !== null) {
            return $relation->getAddressee()->getId() === $user->getId() && !$relation->isAccepted() ? $this->accept($user, $other) : 'social.friend.exists';
        }

        if (!$this->allows($other, $user, 'friend_request')) {
            return 'social.friend.closed';
        }

        $this->friendships->save((new Friendship())->setRequester($user)->setAddressee($other), true);
        $this->notifications->notify($other, NotificationTypeEnum::FRIEND_REQUEST, $this->sender($user));

        return null;
    }

    public function accept(User $user, User $requester): ?string
    {
        $relation = $this->friendships->findOneBy(['requester' => $requester, 'addressee' => $user, 'status' => FriendshipStatusEnum::PENDING]);

        if ($relation === null) {
            return 'social.friend.missing';
        }

        $this->friendships->save($relation->setStatus(FriendshipStatusEnum::ACCEPTED), true);
        $this->notifications->notify($requester, NotificationTypeEnum::FRIEND_ACCEPTED, $this->sender($user));
        $this->badges->evaluate($user);
        $this->badges->evaluate($requester);

        return null;
    }

    public function remove(User $user, User $other): void
    {
        $relation = $this->relation($user, $other);

        if ($relation !== null) {
            $this->friendships->delete($relation, true);
        }
    }

    public function block(User $user, User $other): void
    {
        if ($other->getId() === $user->getId() || $this->blocks->findOneBy(['blocker' => $user, 'blocked' => $other]) !== null) {
            return;
        }

        $this->remove($user, $other);
        $this->blocks->save((new UserBlock())->setBlocker($user)->setBlocked($other), true);
    }

    public function unblock(User $user, User $other): void
    {
        $block = $this->blocks->findOneBy(['blocker' => $user, 'blocked' => $other]);

        if ($block !== null) {
            $this->blocks->delete($block, true);
        }
    }

    public function blocked(User $user): array
    {
        return $this->blocks->findBy(['blocker' => $user], ['id' => 'DESC']);
    }

    public function isBlocked(User $user, User $other): bool
    {
        return $this->blocks->findOneBy(['blocker' => $user, 'blocked' => $other]) !== null
            || $this->blocks->findOneBy(['blocker' => $other, 'blocked' => $user]) !== null;
    }

    public function hasBlocked(User $user, User $other): bool
    {
        return $this->blocks->findOneBy(['blocker' => $user, 'blocked' => $other]) !== null;
    }

    public function allows(User $target, User $from, string $action): bool
    {
        if ($target->isGuest() || $this->isBlocked($target, $from)) {
            return false;
        }

        return match ($target->getPreferences()->get('privacy', $action)) {
            'everyone' => true,
            'friends' => $action === 'friend_request' ? $this->mutual($target, $from) > 0 : $this->areFriends($target, $from),
            default => false,
        };
    }

    public function mutual(User $user, User $other): int
    {
        $ids = fn (User $owner): array => array_map(fn (User $friend): int => $friend->getId(), $this->friends($owner));

        return count(array_intersect($ids($user), $ids($other)));
    }

    public function currentRoom(User $user): ?Room
    {
        $members = $this->members->findBy(['user' => $user], ['seenAt' => 'DESC'], 1);

        return ($members[0] ?? null)?->getRoom();
    }

    public function invite(User $user, User $other): ?string
    {
        $room = $this->currentRoom($user);

        if ($room === null) {
            return 'social.invite.no_room';
        }

        if (!$this->allows($other, $user, 'invite')) {
            return 'social.invite.closed';
        }

        $this->notifications->notify($other, NotificationTypeEnum::ROOM_INVITE, $this->sender($user) + ['room' => $room->getCode(), 'roomName' => $room->getName()]);

        return null;
    }

    public function recentPlayers(User $user): array
    {
        $rows = $this->users->getOrm()->getConnection()->fetchAllAssociative(
            'SELECT r2.user_id, MAX(r2.created_at) AS played, MAX(g.name) AS game FROM game_result r1'
            . ' JOIN game_result r2 ON r2.match_id = r1.match_id AND r2.user_id <> r1.user_id'
            . ' JOIN `user` u ON u.id = r2.user_id AND u.guest = 0 AND u.deletion_requested_at IS NULL'
            . ' JOIN game g ON g.id = r1.game_id'
            . ' WHERE r1.user_id = :user GROUP BY r2.user_id ORDER BY played DESC LIMIT 30',
            ['user' => $user->getId()],
        );
        $recent = [];

        foreach ($rows as $row) {
            $other = $this->users->find((int) $row['user_id']);

            if ($other !== null && $this->relation($user, $other) === null && !$this->isBlocked($user, $other)) {
                $recent[] = ['user' => $other, 'game' => $row['game'], 'date' => $row['played']];
            }
        }

        return array_slice($recent, 0, self::RECENT);
    }

    public function sender(User $user): array
    {
        return [
            'from' => $user->getId(),
            'name' => $user->getUsername(),
            'initials' => $user->getInitials(),
            'avatar' => $user->getAvatar(),
        ];
    }

    protected function all(User $user): array
    {
        return [
            ...$this->friendships->findBy(['requester' => $user]),
            ...$this->friendships->findBy(['addressee' => $user]),
        ];
    }
}
