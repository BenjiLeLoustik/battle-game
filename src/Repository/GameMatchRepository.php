<?php

namespace App\Repository;

use App\Entity\GameMatch;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameMatchRepository extends AbstractRepository
{
    protected string $entityClass = GameMatch::class;
}