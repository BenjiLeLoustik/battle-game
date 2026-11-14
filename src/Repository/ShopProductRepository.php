<?php

namespace App\Repository;

use App\Entity\ShopProduct;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ShopProductRepository extends AbstractRepository
{
    protected string $entityClass = ShopProduct::class;
}
