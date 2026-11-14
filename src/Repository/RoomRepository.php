<?php

namespace App\Repository;

use App\Entity\Room;
use App\Entity\User;
use App\Shared\Room\Enum\RoomVisibilityEnum;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class RoomRepository extends AbstractRepository
{
    protected string $entityClass = Room::class;

    public function findByCode(string $code): ?Room
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findPublic(): array
    {
        return $this->findBy(['visibility' => RoomVisibilityEnum::PUBLIC], ['updatedAt' => 'DESC']);
    }

    public function findJoinedBy(User $user): array
    {
        $ids = $this->getOrm()->getConnection()->fetchFirstColumn(
            'SELECT r.id FROM room r JOIN room_member m ON m.room_id = r.id WHERE m.user_id = :user ORDER BY m.seen_at DESC',
            ['user' => $user->getId()],
        );

        return array_values(array_filter(array_map(fn (mixed $id): ?Room => $this->find((int) $id), $ids)));
    }
}
