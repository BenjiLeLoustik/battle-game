<?php

namespace App\Shared\Clan;

use App\Entity\Clan;
use App\Entity\ClanMember;
use App\Entity\ClanRequest;
use App\Entity\User;
use App\Repository\ClanMemberRepository;
use App\Repository\ClanRepository;
use App\Repository\ClanRequestRepository;
use App\Repository\SeasonRepository;
use App\Shared\Clan\Enum\ClanRoleEnum;

class ClanManager
{
    public const MAX_MEMBERS = 30;

    public function __construct(
        protected ClanRepository $clans,
        protected ClanMemberRepository $members,
        protected ClanRequestRepository $requests,
        protected SeasonRepository $seasons,
    ) {
    }

    public function membership(User $user): ?ClanMember
    {
        return $this->members->findOneBy(['user' => $user]);
    }

    public function create(User $user, string $name, string $tag, string $description, string $color, bool $open): Clan
    {
        $name = mb_substr(trim($name), 0, 40);
        $tag = strtoupper(trim($tag));

        if ($user->isGuest() || $this->membership($user) !== null || mb_strlen($name) < 3 || !preg_match('/^[A-Z0-9]{2,5}$/', $tag) || $this->clans->findOneBy(['name' => $name]) !== null || $this->clans->findOneBy(['tag' => $tag]) !== null) {
            throw new ClanException();
        }

        $clan = (new Clan())->setName($name)->setTag($tag)->setDescription(mb_substr(trim($description), 0, 300))->setColor(preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : '#2EE6D6')->setOpen($open);
        $this->clans->save($clan, true);
        $this->members->save((new ClanMember())->setClan($clan)->setUser($user)->setRole(ClanRoleEnum::OWNER), true);

        return $clan;
    }

    public function update(Clan $clan, string $description, string $color, bool $open): void
    {
        $this->clans->save($clan->setDescription(mb_substr(trim($description), 0, 300))->setColor(preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? strtoupper($color) : $clan->getColor())->setOpen($open), true);
    }

    public function join(Clan $clan, User $user): bool
    {
        if ($user->isGuest() || $this->membership($user) !== null || count($this->list($clan)) >= self::MAX_MEMBERS) {
            throw new ClanException();
        }

        if ($clan->isOpen()) {
            $this->members->save((new ClanMember())->setClan($clan)->setUser($user), true);
            $this->clearRequests($user);

            return true;
        }

        if ($this->requests->findOneBy(['clan' => $clan, 'user' => $user]) === null) {
            $this->requests->save((new ClanRequest())->setClan($clan)->setUser($user), true);
        }

        return false;
    }

    public function answer(ClanRequest $request, bool $accept): void
    {
        $user = $request->getUser();
        $clan = $request->getClan();
        $this->requests->delete($request, true);

        if ($accept && $this->membership($user) === null && count($this->list($clan)) < self::MAX_MEMBERS) {
            $this->members->save((new ClanMember())->setClan($clan)->setUser($user), true);
            $this->clearRequests($user);
        }
    }

    public function leave(User $user): void
    {
        $member = $this->membership($user);

        if ($member === null) {
            return;
        }

        $clan = $member->getClan();
        $this->members->delete($member, true);

        if ($member->getRole() === ClanRoleEnum::OWNER) {
            $others = $this->list($clan);

            if ($others === []) {
                $this->clans->delete($clan, true);

                return;
            }

            usort($others, fn (ClanMember $a, ClanMember $b): int => ($b->getRole() === ClanRoleEnum::OFFICER) <=> ($a->getRole() === ClanRoleEnum::OFFICER) ?: $a->getId() <=> $b->getId());
            $this->members->save($others[0]->setRole(ClanRoleEnum::OWNER), true);
        }
    }

    public function kick(ClanMember $actor, ClanMember $target): void
    {
        if (!$this->outranks($actor, $target)) {
            throw new ClanException();
        }

        $this->members->delete($target, true);
    }

    public function promote(ClanMember $actor, ClanMember $target, ClanRoleEnum $role): void
    {
        if ($actor->getRole() !== ClanRoleEnum::OWNER || $target->getId() === $actor->getId()) {
            throw new ClanException();
        }

        if ($role === ClanRoleEnum::OWNER) {
            $this->members->save($actor->setRole(ClanRoleEnum::OFFICER));
        }

        $this->members->save($target->setRole($role), true);
    }

    public function disband(Clan $clan): void
    {
        $this->clans->delete($clan, true);
    }

    public function list(Clan $clan): array
    {
        $members = $this->members->findBy(['clan' => $clan], ['id' => 'ASC']);
        $order = [ClanRoleEnum::OWNER->value => 0, ClanRoleEnum::OFFICER->value => 1, ClanRoleEnum::MEMBER->value => 2];
        usort($members, fn (ClanMember $a, ClanMember $b): int => $order[$a->getRole()->value] <=> $order[$b->getRole()->value] ?: $a->getId() <=> $b->getId());

        return $members;
    }

    public function pending(Clan $clan): array
    {
        return $this->requests->findBy(['clan' => $clan], ['id' => 'ASC']);
    }

    public function ranking(int $limit = 50): array
    {
        $season = $this->seasons->current();
        $params = [];
        $range = '';

        if ($season !== null) {
            $range = ' AND r.created_at >= :since AND r.created_at < :until';
            $params = ['since' => $season->getStartsAt()->format('Y-m-d H:i:s'), 'until' => $season->getEndsAt()->format('Y-m-d H:i:s')];
        }

        return $this->clans->getOrm()->getConnection()->fetchAllAssociative(
            'SELECT c.id, c.name, c.tag, c.color, c.open, c.description, COUNT(DISTINCT m.user_id) AS members, COALESCE(SUM(r.points), 0) AS points'
            . ' FROM clan c JOIN clan_member m ON m.clan_id = c.id LEFT JOIN game_result r ON r.user_id = m.user_id' . $range
            . ' GROUP BY c.id, c.name, c.tag, c.color, c.open, c.description ORDER BY points DESC, members DESC, c.id ASC LIMIT ' . $limit,
            $params,
        );
    }

    public function points(Clan $clan): array
    {
        $season = $this->seasons->current();
        $params = ['clan' => $clan->getId()];
        $range = '';

        if ($season !== null) {
            $range = ' AND r.created_at >= :since AND r.created_at < :until';
            $params += ['since' => $season->getStartsAt()->format('Y-m-d H:i:s'), 'until' => $season->getEndsAt()->format('Y-m-d H:i:s')];
        }

        return $this->clans->getOrm()->getConnection()->fetchAllKeyValue(
            'SELECT m.user_id, COALESCE(SUM(r.points), 0) FROM clan_member m LEFT JOIN game_result r ON r.user_id = m.user_id' . $range . ' WHERE m.clan_id = :clan GROUP BY m.user_id',
            $params,
        );
    }

    protected function outranks(ClanMember $actor, ClanMember $target): bool
    {
        $rank = [ClanRoleEnum::OWNER->value => 0, ClanRoleEnum::OFFICER->value => 1, ClanRoleEnum::MEMBER->value => 2];

        return $actor->getClan()->getId() === $target->getClan()->getId() && $actor->getRole()->canManage() && $rank[$actor->getRole()->value] < $rank[$target->getRole()->value];
    }

    protected function clearRequests(User $user): void
    {
        foreach ($this->requests->findBy(['user' => $user]) as $request) {
            $this->requests->delete($request);
        }

        $this->requests->getOrm()->flush();
    }
}
