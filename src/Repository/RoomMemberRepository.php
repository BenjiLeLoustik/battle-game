<?php

namespace App\Repository;

use App\Entity\Room;
use App\Entity\RoomMember;
use App\Entity\User;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class RoomMemberRepository extends AbstractRepository
{
    protected string $entityClass = RoomMember::class;

    public function findMember(Room $room, User $user): ?RoomMember
    {
        return $this->findOneBy(['room' => $room, 'user' => $user]);
    }

    public function findByRoom(Room $room): array
    {
        return $this->findBy(['room' => $room], ['id' => 'ASC']);
    }
}
