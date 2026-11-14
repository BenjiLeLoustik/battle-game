<?php

namespace App\Repository;

use App\Entity\SeasonPass;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class SeasonPassRepository extends AbstractRepository
{
    protected string $entityClass = SeasonPass::class;
}
