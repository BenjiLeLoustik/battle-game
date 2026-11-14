<?php

namespace App\Repository;

use App\Entity\AdminRole;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class AdminRoleRepository extends AbstractRepository
{
    protected string $entityClass = AdminRole::class;
}
