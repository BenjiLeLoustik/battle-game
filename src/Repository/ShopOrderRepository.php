<?php

namespace App\Repository;

use App\Entity\ShopOrder;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ShopOrderRepository extends AbstractRepository
{
    protected string $entityClass = ShopOrder::class;
}
