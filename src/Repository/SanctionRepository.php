<?php

namespace App\Repository;

use App\Entity\Sanction;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class SanctionRepository extends AbstractRepository
{
    protected string $entityClass = Sanction::class;
}
