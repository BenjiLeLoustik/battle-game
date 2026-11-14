<?php

namespace App\Repository;

use App\Entity\PassPlan;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class PassPlanRepository extends AbstractRepository
{
    protected string $entityClass = PassPlan::class;
}
