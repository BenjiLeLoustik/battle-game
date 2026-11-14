<?php

namespace App\Repository;

use App\Entity\Banner;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class BannerRepository extends AbstractRepository
{
    protected string $entityClass = Banner::class;
}
