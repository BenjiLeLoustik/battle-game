<?php

namespace App\Repository;

use App\Entity\ClanRequest;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ClanRequestRepository extends AbstractRepository
{
    protected string $entityClass = ClanRequest::class;
}
