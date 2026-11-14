<?php

namespace App\Repository;

use App\Entity\GameEvent;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameEventRepository extends AbstractRepository
{
    protected string $entityClass = GameEvent::class;
}
