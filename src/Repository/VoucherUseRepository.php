<?php

namespace App\Repository;

use App\Entity\VoucherUse;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class VoucherUseRepository extends AbstractRepository
{
    protected string $entityClass = VoucherUse::class;
}
