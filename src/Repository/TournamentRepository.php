<?php

namespace App\Repository;

use App\Entity\Tournament;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class TournamentRepository extends AbstractRepository
{
    protected string $entityClass = Tournament::class;
}
