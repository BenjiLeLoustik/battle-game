<?php

namespace App\Repository;

use App\Entity\Report;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ReportRepository extends AbstractRepository
{
    protected string $entityClass = Report::class;
}
