<?php

namespace App\Repository;

use App\Entity\UserBlock;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class UserBlockRepository extends AbstractRepository
{
    protected string $entityClass = UserBlock::class;
}
