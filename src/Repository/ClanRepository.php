<?php

namespace App\Repository;

use App\Entity\Clan;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ClanRepository extends AbstractRepository
{
    protected string $entityClass = Clan::class;
}
