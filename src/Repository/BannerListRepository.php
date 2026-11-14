<?php

namespace App\Repository;

use App\Entity\BannerList;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class BannerListRepository extends AbstractRepository
{
    protected string $entityClass = BannerList::class;
}
