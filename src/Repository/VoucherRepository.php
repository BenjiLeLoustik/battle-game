<?php

namespace App\Repository;

use App\Entity\Voucher;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class VoucherRepository extends AbstractRepository
{
    protected string $entityClass = Voucher::class;
}
