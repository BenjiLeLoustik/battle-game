<?php

namespace App\Repository;

use App\Entity\Room;
use App\Entity\RoomMessage;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class RoomMessageRepository extends AbstractRepository
{
    protected string $entityClass = RoomMessage::class;

    public function findLatest(Room $room, int $limit): array
    {
        return array_reverse($this->findBy(['room' => $room], ['id' => 'DESC'], $limit));
    }
}
